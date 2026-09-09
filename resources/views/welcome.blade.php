<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'NoWhere') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6f6f6;
            color: #1a1a1a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .card {
            max-width: 420px;
            padding: 48px 40px;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06);
            text-align: center;
        }
        h1 { font-size: 24px; letter-spacing: -0.5px; }
        p { margin-top: 12px; font-size: 14px; line-height: 1.7; color: #666; }
        .btn {
            display: inline-block;
            margin-top: 28px;
            padding: 12px 28px;
            border-radius: 999px;
            background: #1a1a1a;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ config('app.name', 'NoWhere') }}</h1>
        <p>배차 관리 서비스입니다. 로그인하여 운행 등록·수행과 정산을 이용하세요.</p>
        <a class="btn" href="{{ config('app.frontend_url') }}">로그인</a>
    </div>
</body>
</html>
