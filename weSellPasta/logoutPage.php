<?php   
session_start();  
unset($_SESSION["username"]);  
unset($_SESSION["bio"]);  
unset($_SESSION["color"]);  
unset($_SESSION["loggedin"]);  

session_destroy();  
echo "<script type='text/javascript'>
                location.replace('loginPage.php');
            </script>"; 
?>  
