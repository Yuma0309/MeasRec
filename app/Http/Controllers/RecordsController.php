<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Record; //Recordモデルを使えるようにする
use App\Models\Title; //Titleモデルを使えるようにする
use Validator; //バリデーションを使えるようにする
use Auth; //認証モデルを使用する
use Carbon\Carbon; // Carbonクラスを使用する

class RecordsController extends Controller
{
    //コンストラクタ（このクラスが呼ばれたら最初に処理をする）
    public function __construct()
    {
        //ログイン認証後にだけ表示
        $this->middleware('auth');
    }
    
    // 測定値一覧表示
    public function index(Request $request){

        // タイトルが1つもない場合、タイトル保存画面を表示
        $titles = Title::where('user_id', Auth::user()->id)->count();
        if ($titles === 0) {
            return redirect('/titles');
        }

        $id = $request->id;

        $titles = Title::where('user_id', Auth::user()->id)->find($id);

        // タイトルがnullの場合、最初の1レコードを表示
        if (is_null($titles)) { //$sortの初期値（値がない場合）
            $titles = Title::where('user_id', Auth::user()->id)->first();
            $id = $titles->id;
        }

        // chartGet関数に値を送る
        session(['id' => $id]);

        $sort = $request->sort;
        if (is_null($sort)) { //$sortの初期値（値がない場合）
            $sort = 'created_at';
        }

        $page = $request->page;

        // 測定値一覧の項目のボタンが押された回数に応じて昇順と降順を切り替える（奇数回なら昇順、偶数回なら降順）
        $sort_number = $request->sort_number;
        if (is_null($sort_number)) { //$sort_numberの初期値（値がない場合）：'/'にリダイレクト後
            $sort_number = 0;
            $sort_order = 'desc';
        } else {
            if (!is_numeric($page)) { //$pageがnullの場合：測定値一覧の項目のボタンを押すと'/'にリダイレクトして自動的に$pageがnullになる
                $sort_number = $sort_number + 1;
                if ($sort_number % 2 == 0) {
                    $sort_order = 'desc';
                } else {
                    $sort_order = 'asc';
                }
            } else { //$pageが数値の場合：ペジネーションのボタンを押すと自動的に$pageが数値になる
                if ($sort_number % 2 == 0) {
                    $sort_order = 'desc';
                } else {
                    $sort_order = 'asc';
                }
            }
        }

        $records = Record::where('user_id', Auth::user()->id)
            ->where('title_id', $id)
            ->orderBy($sort, $sort_order)
            ->paginate(10);
        
        // $keywordに値があれば検索する
        $keyword = $request->keyword;
        if (is_null($keyword)) { //$keywordの初期値（値がない場合）
            $keyword = '';
        } else {
            $records = Record::where('user_id', Auth::user()->id)
                        ->where('title_id', $id)
                        ->where(function ($query) use ($keyword) {

                            // 全角スペースを半角に変換
                            $spaceConversion = mb_convert_kana($keyword, 's');

                            // 単語を半角スペースで区切り、配列にする（例："10 178" → ["10", "178"]）
                            $wordArraySearched = preg_split('/[\s,、]+/', $spaceConversion, -1, PREG_SPLIT_NO_EMPTY);

                            // 単語をループで回し、レコードと部分一致するものがあれば、$queryとして保持される
                            foreach($wordArraySearched as $value) {
                                $query->where('date', 'LIKE', "%{$value}%")
                                    ->orWhere('amount', 'LIKE', "%{$value}%")
                                    ->orWhere('comment', 'LIKE', "%{$value}%");
                            }

                        })
                        ->orderBy($sort, $sort_order)
                        ->paginate(10);

            session()->flash('message', '検索しました');
        }

        return view('records', [
            'titles' => $titles,
            'sort' => $sort,
            'sort_number' => $sort_number,
            'keyword' => $keyword,
            'records' => $records,
            'page' => $page
        ]);
    }
    
    // チャートデータを取得
    public function chartGet(){

        $id = session('id');

        $titles = Title::where('user_id', Auth::user()->id)->find($id);

        $records = Record::where('user_id', Auth::user()->id)
            ->where('title_id', $id)
            ->orderBy('date', 'asc')
            ->get();
        
        $collection = collect($records);
        $date_max = $collection->max('date');
        $date_min = $collection->min('date');
        $date_max_obj = new Carbon($date_max);
        $date_min_obj = new Carbon($date_min);
        $interval = $date_max_obj->diffInDays($date_min_obj, true);

        if ($interval > 1) { // $records->dateの最大値と最小値の日にちの差が1日以上あれば実行
            for ($i = 1; $i <= $interval; $i++) {
                $date_min_obj->addDays(1);
                $date_search = Record::where('user_id', Auth::user()->id)
                    ->where('title_id', $id)
                    ->where('date', $date_min_obj->format('Y-m-d'))
                    ->get();
                $collection = collect($date_search);
                if ($collection->isEmpty()) { // $records->dateの最大値と最小値の日にちの間で、測定値がない日があればnullを入れる
                    $number = count($records); // $recordsの個数はforで繰り返されるたびに増えていく
                    $records[$number] = new Record;
                    $records[$number]->date = $date_min_obj->format('Y-m-d');
                    $records[$number]->amount = null;
                }
            }
        }

        $collection = collect($records);
        $records = $collection->sortBy('date')->values(); // $recordsのオブジェクトを日付の昇順に並べ替える
        
        $date = []; // chartjs.jsに値を渡すための配列を作成
        $amount = [];
        $title_name = [];

        for ($i = 0; $i < count($records); $i++) { // 配列$dateと$amountに$records->dateと$records->amountの値をそれぞれ入れる
            $date[] = $records[$i]->date;
            $amount[] = $records[$i]->amount;
        }

        $title_name[] = $titles->title;

        return [
            'date' => $date, 
            'amount' => $amount,
            'title_name' => $title_name
        ];
    }

    // 測定値保存処理
    public function store(Request $request){

        // バリデーション
        $validator = Validator::make($request->all(), [
            'date' => 'required',
            'amount' => 'required|min:1|max:9',
            'comment' => 'required|min:1',
        ]);

        $id = $request->id;

        $titles = Title::where('user_id', Auth::user()->id)->find($id);

        $sort = $request->sort;
        if (is_null($sort)) { //$sortの初期値（値がない場合）
            $sort = 'created_at';
        }

        $records = Record::where('user_id', Auth::user()->id)
            ->where('title_id', $id)
            ->orderBy($sort, 'asc')
            ->paginate(10);

        //バリデーション：エラー
        if ($validator->fails()) {
            return redirect('/?id='.$titles->id)->withErrors($validator);
        }        

        //Eloquentモデル（保存処理）
        $records = new Record;
        $records->user_id = Auth::user()->id;
        $records->title_id = $id;
        $records->date = $request->date;
        $records->amount = $request->amount;
        $records->comment = $request->comment;
        $records->save();
        
        session()->flash('message', '保存しました');
        
        return redirect('/?id='.$titles->id);
    }

    // 測定値検索処理
    public function search(Request $request){

        $keyword = $request->input('keyword');

        $id = $request->id;

        $titles = Title::where('user_id', Auth::user()->id)->find($id);

        $sort = $request->sort;

        $sort_number = $request->sort_number;

        return redirect('/?id='.$titles->id.'&sort='.$sort.'&sort_number='.$sort_number.'&keyword='.$keyword);
    }

    // 測定値編集画面表示
    public function edit(Request $request) {

        $record_id = $request->record_id;

        $records = Record::where('user_id', Auth::user()->id)->find($record_id);

        $id = $records->title_id;

        $titles = Title::where('user_id', Auth::user()->id)->find($id);

        $page = $request->page;

        return view('recordsedit', [
            'record' => $records,
            'titles' => $titles,
            'page' => $page
        ]);
    }
    
    // 測定値編集処理
    public function update(Request $request){

        // バリデーション
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'date' => 'required',
            'amount' => 'required|min:1|max:9',
            'comment' => 'required|min:1',
        ]);

        $sort = 'created_at';

        $id = $request->id;

        $records = Record::where('user_id', Auth::user()->id)->find($id);

        $title_id = $records->title_id;

        $titles = Title::where('user_id', Auth::user()->id)->find($title_id);

        $page = $request->page;

        // バリデーション：エラー
        if ($validator->fails()) {
            return view('recordsedit', [
                'record' => $records,
                'titles' => $titles,
                'page' => $page
            ])->withErrors($validator);
        }

        // データ編集
        $records = Record::where('user_id', Auth::user()->id)->find($request->id);
        $records->date = $request->date;
        $records->amount = $request->amount;
        $records->comment = $request->comment;
        $records->save();
        
        session()->flash('message', '保存しました');

        return redirect('/?id='.$titles->id.'&page='.$page);
    }

    // 測定値削除処理
    public function destroy(Record $record) {

        $sort = 'created_at';

        $id = $record->id;

        $records = Record::where('user_id', Auth::user()->id)->find($id);

        $title_id = $records->title_id;

        $titles = Title::where('user_id', Auth::user()->id)->find($title_id);

        $record->delete();

        session()->flash('message', '削除しました');

        return redirect('/?id='.$titles->id);
    }
    
}
