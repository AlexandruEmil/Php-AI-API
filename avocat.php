<?php

// Configurare baza de date
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "chatbot_db";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Eroare conexiune: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Preluare mesaj de la utilizator
    $text = $_POST['message'] ?? '';
    $phone = 'user_web'; // Identificator pentru utilizatorul de pe site

    // Salvare mesaj în DB
    $stmt = $conn->prepare("INSERT INTO messages (user_phone, message, role) VALUES (?, ?, 'user')");
    $stmt->bind_param("ss", $phone, $text);
    $stmt->execute();
    $stmt->close();

    // Istoric conversație
    $history = "";
    $sql = "SELECT message, role FROM messages WHERE user_phone=? ORDER BY id ASC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history .= ($row['role'] == 'user' ? "User: " : "AI: ") . $row['message'] . "\n";
    }
    $stmt->close();

    // Trimitere la OpenAI GPT
    $openai_api_key = ""; // Schimbă cu cheia ta reală
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $openai_api_key",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "model" => "gpt-4o",
        "messages" => [
            ["role" => "system", "content" => "Ești un avocat virtual. Răspunde clar și concis."],
            ["role" => "user", "content" => $history]
        ]
    ]));

    $response = json_decode(curl_exec($ch), true);
    if (!$response) {
        error_log("Eroare cURL: " . curl_error($ch)); // Logare eroare cURL
    } else {
        error_log("Răspuns OpenAI: " . json_encode($response)); // Logare răspuns OpenAI
    }
    curl_close($ch);

    // Verificare erori din răspuns
    if (isset($response['error'])) {
        error_log("Eroare OpenAI: " . $response['error']['message']);
    }

    // Răspuns din OpenAI
    $reply = $response['choices'][0]['message']['content'] ?? "Nu am un răspuns momentan.";

    // Salvare răspuns AI
    $stmt = $conn->prepare("INSERT INTO messages (user_phone, message, role) VALUES (?, ?, 'ai')");
    $stmt->bind_param("ss", $phone, $reply);
    $stmt->execute();
    $stmt->close();
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Juridic AI</title>
    <style>
        /* Stilizare pentru interfață */
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        #chat-container {
            width: 400px;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        #chat-log {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .chat-message {
            margin: 5px 0;
        }
        .user-message {
            text-align: right;
            color: #007bff;
        }
        .ai-message {
            text-align: left;
            color: #28a745;
        }
        #message-form {
            display: flex;
        }
        #message-input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 5px;
        }
        #send-button {
            padding: 8px 15px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div id="chat-container">
        <div id="chat-log">
            <!-- Afișare mesaje din DB -->
            <?php
            $conn = new mysqli($servername, $username, $password, $dbname);
            $sql = "SELECT message, role FROM messages WHERE user_phone='user_web' ORDER BY id ASC";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $class = $row['role'] == 'user' ? 'user-message' : 'ai-message';
                    echo "<div class='chat-message $class'>" . htmlspecialchars($row['message']) . "</div>";
                }
            }
            $conn->close();
            ?>
        </div>
        <form id="message-form" method="POST">
            <input type="text" id="message-input" name="message" placeholder="Scrie un mesaj..." required>
            <button type="submit" id="send-button">Trimite</button>
        </form>
    </div>
</body>
</html>
