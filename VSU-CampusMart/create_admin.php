
<?php
require_once "config/database.php";

$school_id = "admin001";
$full_name = "System Administrator";
$password = "Admin123!";
$role = "admin";
$status = "approved";

// Check if admin already exists
$check = $pdo->prepare(
    "SELECT id FROM users WHERE school_id = ?"
);
$check->execute([$school_id]);

if ($check->fetch()) {
    echo "Admin account already exists!";
} else {

   
    $hashed_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    
    $sql = "INSERT INTO users
            (school_id, full_name, password, role, status)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([
        $school_id,
        $full_name,
        $hashed_password,
        $role,
        $status
    ])) {
        echo "Admin account created successfully!";
    } else {
        echo "Failed to create admin account.";
    }
}
?>
