<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('set_flash_message')) {
    function set_flash_message($type, $message, $title = null) {
        $default_titles = [
            'success' => 'نجاح!',
            'error'   => 'خطأ!',
            'warning' => 'تحذير!',
            'info'    => 'معلومة'
        ];

        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'title' => $title ?? $default_titles[$type] ?? '',
            'message' => $message,
            'timeout' => 3000
        ];
    }

    function set_success_message($message, $title = null) {
        set_flash_message('success', $message, $title);
    }

    function set_error_message($message, $title = null) {
        set_flash_message('error', $message, $title);
    }

    function display_messages() {
        if (empty($_SESSION['flash_messages'])) {
            return;
        }

        echo '<script src="js/sweetalert2@11.js"></script><script>';
        foreach ($_SESSION['flash_messages'] as $msg) {
            echo "Swal.fire({
                icon: '{$msg['type']}',
                title: '{$msg['title']}',
                text: '{$msg['message']}',
                confirmButtonText: 'حسناً',
                timer: {$msg['timeout']},
                timerProgressBar: true
            });";
        }
        echo '</script>';

        unset($_SESSION['flash_messages']);
    }
}
?>