<?php include 'nav.php';

// session_start();
if ($_SESSION["loggedin"]=="yes") {

    $userName=$_SESSION["username"];
    $bio=$_SESSION["bio"];
    $color=$_SESSION["color"];

    ?>

<html>

<head>
    <title>Post Upload Page </title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body><br>
    <center>
        <div id="main-holder"><br>
            <h1>Post Upload Page</h1>

            <form id='myForm' action='processUploadToDatabase.php' method='POST' enctype='multipart/form-data'>

                <input type="file" style="display:none;" accept="image/*" name="fileToUpload" id="fileToUpload">
                <label for="fileToUpload" id="edit">Upload A Photo</label><br><br>

                <input type="text" placeholder="Enter Post Title" name="title"> <br><br>

                <textarea type="text" name="content" rows="10" cols="100" placeholder="Enter Post Content"></textarea><br><br>

                <br><br> <input type="submit" value="Submit Above Post"> </form> <br><br>


            <br>

        </div>
    </center>

    <br><br>
</body>


<?php
}

else {
    echo "<script type='text/javascript'>
location.replace('loginPage.php');
    </script>";

}

?>

</html>