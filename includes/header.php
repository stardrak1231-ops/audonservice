<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ระบบจัดการอู่ซ่อมรถ - บริการซ่อมบำรุงรถยนต์ครบวงจร">
    <meta name="keywords" content="อู่ซ่อมรถ, ซ่อมรถยนต์, บำรุงรักษารถ, เปลี่ยนถ่ายน้ำมัน">
    <title>
        <?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>ระบบจัดการอู่ซ่อมรถ
    </title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts - Prompt (Thai) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'prompt': ['Prompt', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Prompt', sans-serif;
        }

        /* ป้องกันรูปเกินขอบ */
        img {
            max-width: 100%;
            height: auto;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ป้องกัน SVG โดนบีบ */
        svg {
            flex-shrink: 0;
        }
    </style>
</head>

<body class="bg-white text-gray-800 font-prompt">