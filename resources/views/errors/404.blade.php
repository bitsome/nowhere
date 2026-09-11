<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>페이지를 찾을 수 없습니다 — NoWhere</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f4f4;
            color: #1f1f1f;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 420px;
            padding: 40px 32px;
            border: 1px solid #ddd;
            border-radius: 16px;
            background: #fff;
            text-align: center;
        }
        .eyebrow {
            color: #36adff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
        }
        h1 { margin-top: 14px; font-size: 20px; letter-spacing: -0.5px; }
        p { margin-top: 12px; color: #6a6a6a; font-size: 13px; line-height: 1.8; }
        .btn {
            display: block;
            margin-top: 26px;
            padding: 14px 24px;
            border-radius: 12px;
            background: #1f1f1f;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="card">
        <p class="eyebrow">404</p>
        <h1>페이지를 찾을 수 없습니다</h1>
        <p>주소가 바뀌었거나 삭제된 페이지입니다.</p>
        <a class="btn" href="{{ url('/') }}">NoWhere로 가기</a>
    </div>
</body>
</html>
