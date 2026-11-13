
<?php
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrf();

    $name = trim($_POST['full_name'] ?? '');
    $schoolId = trim($_POST['school_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $allowedRoles = ['student', 'teacher', 'seller'];

    if (
        $name === '' ||
        $schoolId === '' ||
        strlen($password) < 8 ||
        !in_array($role, $allowedRoles, true)
    ) {
        $error = 'Complete all fields. Password must contain at least 8 characters.';

    } elseif (!isset($_FILES['school_picture'])) {
        $error = 'Please select a school ID picture.';

    } elseif ($_FILES['school_picture']['error'] !== UPLOAD_ERR_OK) {

        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Picture exceeds the PHP upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'Picture exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL => 'Picture was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'Please select a school picture.',
            UPLOAD_ERR_NO_TMP_DIR => 'PHP temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'PHP cannot save the uploaded picture.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.'
        ];

        $uploadCode = $_FILES['school_picture']['error'];

        $error = $uploadErrors[$uploadCode] ?? 'Unknown upload error: ' . $uploadCode;

    } else {

        // Check duplicate School ID.
        $check = $pdo->prepare(
            "SELECT id FROM users WHERE school_id = ?"
        );

        $check->execute([$schoolId]);

        if ($check->fetch()) {

            $error = 'This School ID Number is already registered.';

        } else {

            // Upload school ID picture.
            $picture = uploadImage(
                $_FILES['school_picture'],
                'school_ids'
            );

            if (!$picture) {

                $error = $GLOBALS['uploadImageError'] ?? 'Picture upload failed.';


            } else {

                try {

                    $hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        INSERT INTO users
                        (
                            school_id,
                            full_name,
                            password,
                            role,
                            school_picture,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->execute([
                        $schoolId,
                        $name,
                        $hash,
                        $role,
                        $picture
                    ]);

                    $success = 'Registration successful. Wait for administrator approval before logging in.';

                } catch (PDOException $e) {

                    // Remove uploaded picture if database insertion fails.
                    $picturePath = $_SERVER['DOCUMENT_ROOT']
                        . '/VSU-CampusMart/uploads/school_ids/'
                        . $picture;

                    if (is_file($picturePath)) {
                        unlink($picturePath);
                    }

                    $error = 'Registration failed. Please check your database fields.';
                    error_log('CampusMart registration error: ' . $e->getMessage());
                }
            }
        }
    }
}

$pageTitle = 'Register';
require_once 'includes/header.php';
?>

<div class="card auth-box">

    <h2>Create an Account</h2>

    <?php if ($error): ?>
        <div class="alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e(csrfToken()) ?>"
        >

        <label>Full Name</label>
        <input
            type="text"
            name="full_name"
            required
        >

        <label>School ID Number</label>
        <input
            type="text"
            name="school_id"
            required
        >

        <label>Password</label>
        <input
            type="password"
            name="password"
            minlength="8"
            required
        >

        <label>Account Type</label>
        <select name="role" required>
            <option value="">Select Account Type</option>
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="seller">Seller</option>
        </select>

        <label>Upload School ID Picture</label>
        <input
            type="file"
            name="school_picture"
            accept="image/*"
            required
        >

        <button type="submit">Register</button>

    </form>

    <p>
        Already registered?
        <a href="login.php">Login here</a>
    </p>

</div>

<?php require_once 'includes/footer.php'; ?>
