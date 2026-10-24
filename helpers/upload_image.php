<?php

require_once __DIR__ . "/../cors.php";

function uploadImageToHostinger(
    $file,
    $folder = "foods_profiles"
) {

    if (
        !isset($file) ||
        $file["error"] !== 0
    ) {
        return null;
    }

    $allowed_extensions = [
        "jpg",
        "jpeg",
        "png",
        "webp"
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
            "Only jpg, jpeg, png, webp files are allowed"
        );
    }

    // Example:
    // htdocs/myproject/uploads/foods_profiles/

    $server_upload_dir =
        dirname(__DIR__) .
        "/uploads/" .
        $folder . "/";

    if (!is_dir($server_upload_dir)) {
        mkdir(
            $server_upload_dir,
            0777,
            true
        );
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
    // the actual project folder name again (previously hardcoded to the
    // placeholder "myproject", which doesn't match this project's folder,
    // Manoharam-Real-Foods-main, and caused 404s on every uploaded image).
    $project_folder = basename(dirname(__DIR__));

    $base_url =
        "http://localhost/" . $project_folder . "/uploads/"
        . $folder . "/";

    return $base_url . $file_name;
}