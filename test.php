<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "POST request received! Name: " . $_POST['name'];
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Form</title>
</head>
<body>
    <form method="POST" action="test.php">
        <input type="text" name="name" placeholder="Your Name" required>
        <button type="submit">Submit</button>
    </form>
</body>
</html>