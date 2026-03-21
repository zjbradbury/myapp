        <?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<html>
    <ul>
        <li><a href="index.php">Home</a></li>

                 <li><a href="allPosts.php">View All Posts</a></li>
                <li><a href="editProfilePage.php">Edit Your Profile</a></li>
                <li><a href="viewProfile.php">View Your Page</a></li>
                <li><a href="postUpload.php">Upload A Post</a></li>

<!--        <li><a href="login-page.html">Login</a></li>
        <li><a href="../UpdateDisplay.php">Admin</a></li>-->

        <?php

if (!isset($_SESSION["loggedin"])) {
    echo '<li style="float:right"><a href="loginPage.php">Login</a></li>';
} else {
    echo '<li style="float:right"><a href="logoutPage.php">Logout</a></li>';
}
?>

        <!-- <li style="float:right"><a href="loginPage.php">Login</a></li> -->

 <!--        <li style="float:right"><a href="/grade11/demo.php">Demo</a></li> -->
    </ul>
    </html>
