<?php
$target_dir = __DIR__ . '/models/';
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

$files = [
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/dist/face-api.min.js' => __DIR__ . '/face-api.min.js',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/ssd_mobilenetv1_model-weights_manifest.json' => $target_dir . 'ssd_mobilenetv1_model-weights_manifest.json',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/ssd_mobilenetv1_model-shard1' => $target_dir . 'ssd_mobilenetv1_model-shard1',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-weights_manifest.json' => $target_dir . 'face_landmark_68_model-weights_manifest.json',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-shard1' => $target_dir . 'face_landmark_68_model-shard1',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-weights_manifest.json' => $target_dir . 'face_recognition_model-weights_manifest.json',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-shard1' => $target_dir . 'face_recognition_model-shard1',
    'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-shard2' => $target_dir . 'face_recognition_model-shard2',
];

foreach ($files as $url => $path) {
    echo "Downloading " . basename($path) . "...\n";
    $content = file_get_contents($url);
    if ($content === false) {
        echo "FAILED to download $url\n";
    } else {
        file_put_contents($path, $content);
        echo "Saved to $path\n";
    }
}
echo "Asset download complete.\n";
