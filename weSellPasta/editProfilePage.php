<?php include 'nav.php';

// session_start();
if ($_SESSION["loggedin"]=="yes") {

    $userName=$_SESSION["username"];
    $bio=$_SESSION["bio"];
    $color=$_SESSION["color"];

    ?>

<html>

<head>
    <title>Profile Page </title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>

<body><br>
    <center>
        <div id="main-holder"><br>
            <h1>Your Profile Page</h1>

<?php 
            
            $conn=mysqli_connect("localhost", "root", "", "bookface");


    if (mysqli_connect_errno()) {
        echo "Failed to connect to database: <br>".mysqli_connect_error();
    }

    else {}

    $query="SELECT * FROM `users` WHERE `username` = '".$userName."' LIMIT 1";
    $result=mysqli_query($conn, $query);
    // $row = mysqli_fetch_array( $result );


    while($row=mysqli_fetch_array($result)) {
        
        echo '<img width="10%" src="' . $row['pic'] . '"> <br>';

        echo "<form id='myForm' action='updateProfile.php' method='POST' enctype='multipart/form-data'>";

        echo '<input type="file" style="display:none;" accept="image/*" name="fileToUpload" id="fileToUpload">
        <label for="fileToUpload" id = "edit" >Edit Profile Picture</label><br><br>';


        echo ' Username: <input type="text" value= "'.$row['username'].'" name="username"> <br><br>';

        echo 'Bio: <textarea type="text" name="bio" rows="10" cols="50">' .$row['bio'] . "</textarea><br><br>";

        echo '<input type="color" id="color" name="color" value="'.$row['color'] .'">';

        echo '<br><br> <input type="submit" value="Update Above Changes"> </form> <br><br>';

        echo "<form id='myForm' action='passwordUpdate.php' method='POST' enctype='multipart/form-data'>";
        echo ' Password: <input type="text" placeholder= "password" name="password"> <input type="submit" value="Update Password"> </form><br><br>';

        //     echo
        // "<br> Bed ".$row['bed'].": <TEXTAREA type='number' name='moist' ROWS='1' COLS='5' >".$row['moist']."</TEXTAREA>%  <input type='hidden' name='bed' value='".$row['bed']."'>";

    }

    // echo "<h1>Username: " . $userName . "</h1>" . "<a class='button' href='#'>EDIT</a>";
    // echo "Bio: <br>" . $bio . "<a class='button' href='#'>EDIT</a>";


    ?>
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