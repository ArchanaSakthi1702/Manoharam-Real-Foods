<?php

require_once __DIR__ . "/../cors.php";

function uploadImageToHostinger(
    $file,
    $folder = "foods_profiles"
) {

    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize limit in php.ini",
            UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form",
            UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary upload folder on the server",
            UPLOAD_ERR_CANT_WRITE => "Failed to write uploaded file to disk",
            UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload",
        ];
        $msg = $uploadErrors[$file["error"]] ?? ("Unknown upload error code " . $file["error"]);
        throw new Exception("Image upload failed: " . $msg);
    }

    $allowed_extensions = [
        "jpg",
        "jpeg",
        "png",
        "webp",
        "gif",
        "avif"
    ];

    $extension = strtolower(
        pathinfo(
            $file["name"],
            PATHINFO_EXTENSION
        )
    );

    if (
        !in_array(
            $extension,
            $allowed_extensions
        )
    ) {
        throw new Exception(
            "Only jpg, jpeg, png, webp, gif, avif files are allowed"
        );
    }

    $server_upload_dir =
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR . "uploads" .
        DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;

    if (!is_dir($server_upload_dir)) {
        if (!mkdir($server_upload_dir, 0777, true) && !is_dir($server_upload_dir)) {
            throw new Exception("Failed to create upload directory: " . $server_upload_dir);
        }
    }

    $file_name =
        uniqid($folder . "_", true)
        . "."
        . $extension;

    $target_path =
        $server_upload_dir .
        $file_name;

    if (
        !move_uploaded_file(
            $file["tmp_name"],
            $target_path
        )
    ) {
        throw new Exception(
            "Image upload failed. Target: "
            . $target_path
        );
    }

    // Derived from the server path so this can't drift out of sync with
    // the actual project folder name
    $project_folder = basename(dirname(__DIR__));

    // Dynamic host and scheme detection with fallback
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "localhost";

    $base_url =
        $scheme . "://" . $host . "/" . $project_folder . "/uploads/"
        . $folder . "/";

    return $base_url . $file_name;
}
