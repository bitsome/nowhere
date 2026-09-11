<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="NoWhere">
    <meta property="og:locale" content="ko_KR">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="{{ $url }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    {{-- 미리보기를 읽은 뒤에는 곧바로 SPA 공개 화면으로 넘긴다 --}}
    <script>location.replace(@json($spaPath));</script>
    <noscript><meta http-equiv="refresh" content="0;url={{ $spaPath }}"></noscript>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f4f4;
            color: #1f1f1f;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif;
            font-size: 13px;
        }
        a { color: #36adff; font-weight: 700; }
    </style>
</head>
<body>
    <p><a href="{{ $spaPath }}">운행 확인하러 가기</a></p>
</body>
</html>
