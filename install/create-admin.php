<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$error = '';
$done = false;
$adminExists = false;

/*
|--------------------------------------------------------------------------
| Check whether an admin already exists
|--------------------------------------------------------------------------
*/

try {
    $stmt = db()->query(
        "SELECT COUNT(*) 
         FROM users 
         WHERE role = 'admin'"
    );

    $adminCount = (int) $stmt->fetchColumn();

    if ($adminCount > 0) {
        $adminExists = true;
    }

} catch (Throwable $e) {
    $error = 'Unable to check existing admin account: ' . $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| Create First Admin
|--------------------------------------------------------------------------
*/

if (
    !$adminExists &&
    $error === '' &&
    is_post()
) {

    try {

        verify_csrf();

        $name = trim(post('name'));
        $email = strtolower(trim(post('email')));
        $password = post('password');
        $confirmPassword = post('confirm_password');


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($name === '') {
            throw new RuntimeException('Admin name is required.');
        }

        if (mb_strlen($name) > 150) {
            throw new RuntimeException('Admin name is too long.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException(
                'Password must contain at least 8 characters.'
            );
        }

        if ($password !== $confirmPassword) {
            throw new RuntimeException('Passwords do not match.');
        }


        /*
        |--------------------------------------------------------------------------
        | Check Email
        |--------------------------------------------------------------------------
        */

        $check = db()->prepare(
            "SELECT id 
             FROM users 
             WHERE email = ? 
             LIMIT 1"
        );

        $check->execute([$email]);

        if ($check->fetch()) {
            throw new RuntimeException(
                'An account with this email address already exists.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Create Admin
        |--------------------------------------------------------------------------
        */

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = db()->prepare(
            "INSERT INTO users (
                name,
                email,
                password_hash,
                role,
                status,
                email_verified_at,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                ?,
                ?,
                'admin',
                'active',
                NOW(),
                NOW(),
                NOW()
            )"
        );

        $stmt->execute([
            $name,
            $email,
            $passwordHash
        ]);

        $done = true;


    } catch (Throwable $e) {

        $error = $e->getMessage();

    }
}

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>Create First Admin</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>


<body class="bg-light">

<div class="container py-5">

    <div
        class="card shadow-sm mx-auto"
        style="max-width:560px;"
    >

        <div class="card-body p-4 p-md-5">


            <h1 class="h3 mb-3">
                Create First Admin
            </h1>


            <?php if ($error !== ''): ?>

                <div class="alert alert-danger">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>



            <?php if ($adminExists): ?>

                <div class="alert alert-info">

                    <strong>
                        An admin account already exists.
                    </strong>

                    <br><br>

                    You do not need to create another administrator.

                </div>


                <a
                    href="<?= e(url('/login')) ?>"
                    class="btn btn-primary"
                >
                    Go to Admin Login
                </a>



            <?php elseif ($done): ?>

                <div class="alert alert-success">

                    <strong>
                        Admin account created successfully.
                    </strong>

                    <br><br>

                    You can now log in using the email and password
                    you just created.

                </div>


                <a
                    href="<?= e(url('/login')) ?>"
                    class="btn btn-success"
                >
                    Login Now
                </a>


                <div class="alert alert-warning mt-4 mb-0">

                    <strong>Security:</strong>

                    After confirming that you can log in,
                    delete or rename the

                    <code>install</code>

                    folder.

                </div>



            <?php else: ?>


                <form
                    method="post"
                    class="d-grid gap-3"
                    autocomplete="off"
                >

                    <?= csrf_field() ?>


                    <div>

                        <label class="form-label">
                            Admin Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            maxlength="150"
                            required
                        >

                    </div>


                    <div>

                        <label class="form-label">
                            Admin Email
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            required
                        >

                    </div>


                    <div>

                        <label class="form-label">
                            Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <div class="form-text">
                            Minimum 8 characters.
                        </div>

                    </div>


                    <div>

                        <label class="form-label">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            class="form-control"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-primary btn-lg"
                    >
                        Create Admin
                    </button>

                </form>


            <?php endif; ?>


        </div>

    </div>

</div>

</body>

</html>