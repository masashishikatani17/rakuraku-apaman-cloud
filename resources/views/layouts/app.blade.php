<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'らくらく社計簿-アパマン編')</title>
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            width: min(1080px, calc(100% - 32px));
            margin: 0 auto;
        }

        .site-header {
            background: #1d4ed8;
            color: #ffffff;
            padding: 16px 0;
            margin-bottom: 24px;
        }

        .site-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
        }

        .site-nav {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .site-nav a {
            color: #dbeafe;
            text-decoration: none;
            font-weight: 600;
        }

        .site-nav a:hover {
            text-decoration: underline;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .page-title {
            margin: 0;
            font-size: 28px;
        }

        .page-description {
            margin: 8px 0 0;
            color: #4b5563;
        }

        .card {
            background: #ffffff;
            border: 1px solid #dbe3f0;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }

        .button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .button-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .button-secondary:hover {
            background: #d1d5db;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .muted {
            color: #6b7280;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        .data-table th {
            background: #f8fafc;
            font-size: 13px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field-full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
        }

        input,
        select,
        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            background: #ffffff;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
        }

        .checkbox-wrap input[type="checkbox"] {
            width: auto;
        }

        .required {
            color: #dc2626;
            font-size: 12px;
            margin-left: 4px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <h1 class="site-title">らくらく社計簿-アパマン編</h1>
            <nav class="site-nav">
                <a href="{{ route('business-owners.index') }}">事業主一覧</a>
                <a href="{{ route('business-owners.create') }}">事業主登録</a>
            </nav>
        </div>
    </header>

    <main class="container">
        @yield('content')
    </main>
</body>
</html>