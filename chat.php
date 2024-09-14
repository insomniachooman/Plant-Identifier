<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

function generateChatResponse($user_message, $plant_info) {
    $api_key = "AIzaSyCqrGOsG-GnKnRSma-LTIKwDHO-D6edXw8";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" . $api_key;

    $prompt = "You are a knowledgeable botanist assistant. A user has identified a plant using an AI tool, and now they want to know more about it. Here's the information about the plant:\n\n" .
        $plant_info . "\n\n" .
        "The user asks: " . $user_message . "\n\n" .
        "Please provide a helpful and informative response based on the plant information and the user's question. If the user asks about plant care, provide detailed instructions on watering, sunlight requirements, soil preferences, and any other relevant care tips. Use markdown formatting to make your response more readable. Use **bold** for important terms, *italics* for scientific names, and bullet points for lists. If you mention any technical terms, wrap them in backticks like `this`.";

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
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (isset($data['message']) && isset($data['plantInfo'])) {
        try {
            $chat_response = generateChatResponse($data['message'], $data['plantInfo']);
            echo json_encode(["response" => $chat_response]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => "An error occurred while processing the chat: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid request. Please provide both 'message' and 'plantInfo'."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
}