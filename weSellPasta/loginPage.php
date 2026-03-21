<?php  
    include 'nav.php';
?> 
<!DOCTYPE html>
<html lang="">

<head>
    <link rel="stylesheet" href="style.css">
    <title>Login</title>

</head>

<body>
<br>
    <center>

    <div id="main-holder">
        <h1>Login</h1>
        <form action="processLogin.php" method="POST">
            <input placeholder="Username" type="text" name="username">
            <input placeholder="Password" type="password" name="password">
            <input type="submit" value="Submit">
        </form>

        <h1>Create User</h1>
        <form action="processCreateUser.php" method="POST">
            <input placeholder="New Username" type="text" name="username">
            <input placeholder="Enter Email" type="email" name="email" required>
            <input placeholder="New Password" type="password" name="password">
            <label for="favcolor">Select your Chat color: </label>
            <input type="color" id="color" name="color" value="#ff0000">
            <input placeholder="" type="submit" value="Submit">
        </form>
        <br>

</div>
    </center>
</body>

</html>
