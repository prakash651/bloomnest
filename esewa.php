<?php
include 'config.php';
session_start();

// Function to generate a unique transaction ID
function generateTransactionID() {
    return uniqid(time() . " - " . mt_rand(), true);
}

// Initialize variables
$amt = 0;
$trans_uuid = "";
$tax = 0;
$total_amt = 0;

if (isset($_GET['amount'])) {
    $amt = $_GET['amount'];
    $trans_uuid = generateTransactionID();
    $product_code = "EPAYTEST";
    $product_service_charge = 0;
    $product_delivery_charge = 0;
    $total_amt = $amt + $tax + $product_service_charge + $product_delivery_charge;
    $parameter = "total_amount,transaction_uuid,product_code";
    $signed_field_names = "total_amount=$total_amt,transaction_uuid=$trans_uuid,product_code=$product_code";
    $secret_key = "8gBm/:&EnhH.1/q";
    $s = hash_hmac("sha256", $signed_field_names, $secret_key, true);
    
    // Store transaction details in session
    $_SESSION['esewa_transaction'] = [
        'uuid' => $trans_uuid,
        'amount' => $amt,
        'status' => 'pending'
    ];
}
?>

<html>
<head>
    <title>Confirmation Page | BloomNest</title>
    <link href="../assets/boxicons/css/boxicons.min.css" rel="stylesheet" />
    <style>
        body {
            margin: 0;
            background: rgb(7, 48, 100);
        }
        h2 {
            color: white;
        }
        .confirmation {
            margin-top: 100px;
        }
        .submit-button {
            color: #FFF;
            background: #44CC44;
            padding: 15px 20px;
            box-shadow: 0 4px 0 0 #2EA62E;
            transition: all 0.1s linear;
        }
        .submit-button:hover {
            background: #6FE76F;
            box-shadow: 0 4px 0 0 #7ED37E;
        }
        .cancel-button {
            color: #FFF;
            background: tomato;
            padding: 15px 20px;
            box-shadow: 0 4px 0 0 #CB4949;
            transition: all 0.1s linear;
        }
        .cancel-button:hover {
            background: rgb(255, 147, 128);
            box-shadow: 0 4px 0 0 #EF8282;
        }
    </style>
</head>
<body>
    <form action="https://rc-epay.esewa.com.np/api/epay/main/v2/form" method="POST" class="bmc-button" id="form">
        <input type="hidden" id="amount" name="amount" value="<?= $amt ?>" required>
        <input type="hidden" id="tax_amount" name="tax_amount" value="<?= $tax ?>" required>
        <input type="hidden" id="total_amount" name="total_amount" value="<?= $total_amt ?>" required>
        <input type="hidden" id="transaction_uuid" name="transaction_uuid" value="<?= $trans_uuid ?>" required>
        <input type="hidden" id="product_code" name="product_code" value="<?= $product_code ?>" required>
        <input type="hidden" id="product_service_charge" name="product_service_charge" value="0" required>
        <input type="hidden" id="product_delivery_charge" name="product_delivery_charge" value="0" required>
        <input type="hidden" id="success_url" name="success_url" value="http://localhost/flower-shop(oop)/index.php" required>
        <input type="hidden" id="failure_url" name="failure_url" value="http://localhost/flower-shop(oop)/index.php" required>
        <input type="hidden" id="signed_field_names" name="signed_field_names" value="total_amount,transaction_uuid,product_code" required>
        <input type="hidden" id="signature" name="signature" value="<?= base64_encode($s) ?>" required>
        
        <center class="confirmation">
            <h2>Are you sure you want to proceed? (Amount : Rs. <?= $amt ?>)</h2>
            <input value="Proceed" type="submit" name="submit" class="submit-button">
            <i class='bx bx-check'></i>
            <input value="Cancel" type="reset" name="cancel" class="cancel-button" onclick="window.location.href='http://localhost/flower-shop(oop)/checkout.php'">
            <i class='bx bx-x'></i>
        </center>
    </form>
</body>
</html>