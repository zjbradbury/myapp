        <?php 
        $conn=mysqli_connect("mariadb", "zack", "password", "table");

    if (mysqli_connect_errno()) {
        echo "Failed to connect to database: <br>".mysqli_connect_error();
    }

    else {}

    $query="SELECT * FROM `table`";
    $result=mysqli_query($conn, $query);
    // $row = mysqli_fetch_array( $result );

    
    while($row=mysqli_fetch_array($result)) {
        echo '<h1>' .$row['row']. '</h1>';
}

    ?>