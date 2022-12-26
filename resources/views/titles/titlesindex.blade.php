@extends('layouts.app')

@section('content')
<div class="text-end">
    <a href="{{ url('titlesadd') }}" class="btn btn-primary mb-3 me-3">
        タイトル追加
    </a>
</div>

@if (session('message'))
    <div class="alert alert-success text-center">
        {{ session('message') }}
    </div>
@endif

<!-- タイトル一覧 -->
@if (isset($titles))
    @if (count($titles) > 0)
        <div style="font-size:15pt;font-weight:bold;" class="text-center my-2 mx-2">
            タイトル一覧
        </div>
        <table class="table table-striped">

            <!-- テーブルヘッダー -->
            <thead>

                    <th>
                    </th>

                    <th>
                    </th>

                    <th>
                    </th>

                    <th>
                    </th>

            </thead>

            <!-- テーブル本体 -->
            <tbody>
                @foreach ($titles as $title)
                    <tr>

                        <!-- タイトル -->
                        <td style="width:35%" class="text-center align-middle">
                            <form action="{{ url('/') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-dark">
                                    {{ $title->title }}
                                </button>
                                <!-- id値を送信 -->
                                <input type="hidden" name="id" value="{{$title->id}}">
                            </form>
                        </td>

                        <!-- 測定値の単位 -->
                        <td style="width:35%" class="text-center align-middle">
                            <div>
                                測定値の単位：{{ $title->unit }}
                            </div>
                        </td>

                        <!-- 編集ボタン -->
                        <td style="width:15%" class="text-center align-middle">
                            <form action="{{ url('titlesedit/'.$title->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-success">
                                    編集
                                </button>
                            </form>
                        </td>

                        <!-- 削除ボタン -->
                        <td style="width:15%" class="text-center align-middle">
                            <form action="{{ url('title/'.$title->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger" onclick='return confirm("本当に削除しますか？");'>
                                    削除
                                </button>
                            </form>
                        </td>

                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- ぺジネーション -->
        <div class="pagination justify-content-center my-4">
            {{ $titles->links()}}
        </div>
    
    @endif
@endif
@endsection