<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

function generateContent($image_data, $mime_type) {
    $api_key = "API_KEY_HERE";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-exp-0827:generateContent?key=" . $api_key;

    $prompt = "Identify this plant and provide the following information in the exact format specified, without any additional text:\n" .
        "[Plant Name]\n" .
        "[Scientific Name]\n" .
        "[Category: Herb, Shrub, or Tree]\n\n" .
        "[A concise description about the plant, 2-3 sentences]\n\n" .
        "Do not include any additional information, explanations, or sources.";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => $mime_type,
                            "data" => base64_encode($image_data)
                        ]
                    ]
                ]
            ]
        ]
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        throw new Exception("Error making API request");
    }

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'];
}

function classifyImage($image_data, $mime_type) {
    $api_key = "AIzaSyCqrGOsG-GnKnRSma-LTIKwDHO-D6edXw8";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-exp-0827:generateContent?key=" . $api_key;

    $prompt = "Classify this image as either 'plant' or 'not plant'. Respond with only one word: either 'plant' or 'not_plant'.";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => $mime_type,
                            "data" => base64_encode($image_data)
                        ]
                    ]
                ]
            ]
        ]
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        throw new Exception("Error making API request");
    }

    $result = json_decode($response, true);
    return trim(strtolower($result['candidates'][0]['content']['parts'][0]['text']));
}

function saveIdentificationResult($user_id, $plant_name, $scientific_name, $category, $description, $image_path) {
    $host = 'localhost';
    $db   = 'plant_identifier';
    $user = 'root';
    $pass = '';

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("INSERT INTO plant_identifications (user_id, plant_name, scientific_name, category, description, image_path, identification_date) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssss", $user_id, $plant_name, $scientific_name, $category, $description, $image_path);

    if (!$stmt->execute()) {
        throw new Exception("Error saving identification result: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
}

function generateMoreInfo($plant_name) {
    $api_key = "AIzaSyCqrGOsG-GnKnRSma-LTIKwDHO-D6edXw8";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro-exp-0827:generateContent?key=" . $api_key;

    $prompt = "Provide more detailed information about the plant '{$plant_name}'. Include details about its habitat, uses, and any interesting facts. Keep the response concise, around 3-4 sentences.";

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($data)
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        throw new Exception("Error making API request");
    }

    $result = json_decode($response, true);
    return $result['candidates'][0]['content']['parts'][0]['text'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'know_more') {
        if (isset($_POST['plant_name'])) {
            try {
                $more_info = generateMoreInfo($_POST['plant_name']);
                echo json_encode(["result" => $more_info]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["error" => "An error occurred while fetching additional information: " . $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Plant name not provided"]);
        }
    } else {
        if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
            $allowed_mime_types = [
                "image/jpeg",
                "image/png",
                "image/gif",
                "image/bmp",
                "image/tiff",
                "image/webp"
            ];

            $filetype = $_FILES["image"]["type"];
            $filesize = $_FILES["image"]["size"];

            if (in_array($filetype, $allowed_mime_types)) {
                if ($filesize > 10 * 1024 * 1024) { // 10 MB limit
                    http_response_code(400);
                    echo json_encode(["error" => "File size exceeds the 10 MB limit."]);
                    exit;
                }

                $image_data = file_get_contents($_FILES["image"]["tmp_name"]);
                
                try {
                    $classification = classifyImage($image_data, $filetype);
                    
                    if ($classification === 'plant') {
                        $result = generateContent($image_data, $filetype);
                        
                        // Save the image file
                        $upload_dir = "uploads/";
                        $image_path = $upload_dir . uniqid() . "_" . basename($_FILES["image"]["name"]);
                        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);

                        // Parse the result
                        $lines = explode("\n", $result);
                        $plant_name = trim($lines[0]);
                        $scientific_name = trim($lines[1]);
                        $category = trim($lines[2]);
                        $description = trim(implode("\n", array_slice($lines, 3)));

                        // Save the identification result
                        session_start();
                        $user_id = $_SESSION['user_id'];
                        saveIdentificationResult($user_id, $plant_name, $scientific_name, $category, $description, $image_path);

                        echo json_encode(["result" => $result, "image_path" => $image_path]);
                    } else {
                        http_response_code(400);
                        echo json_encode(["error" => "The uploaded image does not appear to be a plant. Please upload an image of a plant."]);
                    }
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(["error" => "An error occurred while processing the image: " . $e->getMessage()]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["error" => "Unsupported file type. Please upload a JPEG, PNG, GIF, BMP, TIFF, or WebP image."]);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "No file uploaded or invalid file."]);
            exit;
        }
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}