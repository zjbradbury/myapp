<html>

<head>
    <title>Upload Added</title>
    <link rel="stylesheet" href="style.css">
        <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        .btn {
            background-color: white;
            border: none;
            color: black;
            padding: 12px 16px;
            font-size: 16px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: grey;
        }

    </style>
    <h1 style="color:white">Successfully Uploaded to Database </h1>
</head>

<body>

<center>
    <button class="btn" type="button" onclick="myFunction()">Continue</button>

    <script>
        function myFunction() {
            location.replace("viewProfile.php");
        }

    </script>
    </center>
</body>

</html>
