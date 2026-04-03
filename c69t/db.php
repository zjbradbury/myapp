        <?php 
        $conn=mysqli_connect("mariadb", "zack", "Butcher69", "c69tTable");

    if (mysqli_connect_errno()) {
        echo "Failed to connect to database: <br>".mysqli_connect_error();
    }

    else {}

    $query="SELECT * FROM `c69tTable`";
    $result=mysqli_query($conn, $query);
    // $row = mysqli_fetch_array( $result );

    
    while($row=mysqli_fetch_array($result)) {
        echo '<h1>' .$row['operator']. '</h1>';
}

    ?>