<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dönüştürülüyor... - Playlist Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        #progress-container {
            height: 30px;
            background-color: #e9ecef;
            border-radius: .25rem;
        }
        #progress-bar {
            height: 100%;
            background-color: #007bff;
            width: 0%;
            transition: width .1s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header text-center">
                <h2>Playlist Dönüştürülüyor</h2>
            </div>
            <div class="card-body">
                <p>Lütfen bekleyin, bu işlem biraz zaman alabilir...</p>
                <div id="progress-container" class="mt-3">
                    <div id="progress-bar"></div>
                </div>
                <div id="progress-text" class="text-center mt-2"></div>
            </div>
        </div>
    </div>
</body>
</html>
