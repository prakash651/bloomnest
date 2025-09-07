<?php
session_start();
include "./sidebar.php";
include_once 'adminHeader.php';
require_once 'config/dbconnect.php';
include 'errorreporting.php';
// $_SESSION["id"] = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminname = $_POST['adminname'];
    $password = $_POST['password'];
    $id = $_SESSION["id"];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "UPDATE admin_detail SET admin_name = '$adminname', admin_password = '$hashed_password' WHERE id = '$id'";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo "Admin details updated successfully.";
        $_SESSION["adminname"] = $adminname;
        $_SESSION["id"] = $id;
        header("Refresh:1 ; URL=index.php");
    } else {
        echo "Error updating user details: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Change Admin Detail</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
   <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
       <link rel="stylesheet" href="assets/css/style.css"></link>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">

       <style>
        body{
          /* background:url("assets/images/logo.png") no-repeat center; */
          background-color:pink; 
        }
        .inp-box{
          color:white;
          width: 350px;
          background-color:transparent;
          border: 3px solid white;
        }
       </style>
</head>
<body>
      <div id="main">
        <button class="openbtn" onclick="openNav();" style = "width:85px; border-radius:10px;"><i class="fa fa-gear" style="font-size:60px;"></i></button>
      </div>

        
  <div class="wrapper container center-form">
    <div class="col-md-6">
    <h1 style="color:white;">Settings</h1>
    <form method="post" action="" onsubmit="return validateSettingsForm()">
        <div class="form-group">
          <label for="adminname" style="color:white;">Admin Name:</label>
          <input type="text" class="inp-box input form-control" id="adminname" name="adminname" required>
        </div>
        <div class="form-group">
          <label for="password" style="color:white;">Password:</label>
          <input type="password" class="inp-box input form-control" id="password" name="password" required>
          <button type="submit" class="btn btn-primary">Update</button>
          <button type="button" class="btn btn-primary" onclick="location.href='dashboard.php'">Cancel</button>
        </form>
      </div>
  </div>

    <div class="footer">
        <?php include 'adminfooter.php' ?>
    </div>
<script>
   function validateSettingsForm() {
      // Admin name validation: must not start with a number
      const adminName = document.getElementById('adminname').value.trim();
      const nameRegex = /^[^\d][\w\s]*$/; // Ensures the name doesn't start with a number
      if (!nameRegex.test(adminName)) {
         alert("Admin name must not start with a number.");
         return false;
      }

      // Password validation: must be at least 8 characters, contain one special character, and one capital letter
      // const password = document.getElementById('password').value.trim();
      // const passwordRegex = /^(?=.*[A-Z])(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
      // if (!passwordRegex.test(password)) {
      //    alert("Password must be at least 8 characters long, contain at least one capital letter, and one special character.");
      //    return false;
      // }

      return true; // If both validations pass
   }
</script>

      

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script src="https://code.jquery.com/jquery-3.1.1.min.js" ></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" ></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"></script>
</body>
</html>
