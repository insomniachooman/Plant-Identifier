<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

$host = 'localhost';
$db   = 'plant_identifier';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("An error occurred. Please try again later.");
}

$user_id = $_SESSION['user_id'];

// Fetch user information
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("An error occurred. Please try again later.");
}

// Fetch plant identification history
$stmt = $conn->prepare("SELECT * FROM plant_identifications WHERE user_id = ? ORDER BY category, identification_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$plants = [
    'Herbs' => [],
    'Shrubs' => [],
    'Trees' => []
];

while ($row = $result->fetch_assoc()) {
    $category = ucfirst(strtolower(trim($row['category'])));
    if (!isset($plants[$category])) {
        $category = 'Herbs'; // Default category if not recognized
    }
    $plants[$category][] = $row;
}

// Get the most recent image path
$recent_image_path = '';
foreach ($plants as $category => $categoryPlants) {
    if (!empty($categoryPlants)) {
        $recent_image_path = $categoryPlants[0]['image_path'];
        break;
    }
}

// Fetch account statistics
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_identifications,
    COUNT(DISTINCT DATE(identification_date)) as active_days
    FROM plant_identifications 
    WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

if (!$stats) {
    $stats = ['total_identifications' => 0, 'active_days' => 0];
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            line-height: 1.6;
        }
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?php echo htmlspecialchars($recent_image_path); ?>');
            background-size: cover;
            background-position: center;
            filter: blur(5px);
            z-index: -1;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            position: relative;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .header {
            margin-bottom: 40px;
            text-align: center;
        }
        h1 {
            color: #1877f2;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        h2 {
            color: #1877f2;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .section {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .plant-list {
            display: flex;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
            gap: 25px;
            padding: 20px 0;
            position: relative;
        }
        .plant-list::-webkit-scrollbar {
            display: none; /* WebKit */
        }
        .plant-item {
            flex: 0 0 auto;
            width: 200px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: #ffffff;
        }
        .plant-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .plant-item img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .plant-item h3 {
            font-size: 1.2rem;
            margin-bottom: 5px;
            color: #333;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            background-color: rgba(231, 243, 255, 0.8);
            padding: 25px;
            border-radius: 8px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-item h3 {
            font-size: 1.4rem;
            color: #1877f2;
            margin-bottom: 5px;
        }
        .stat-item p {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #1877f2;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background-color 0.3s ease;
            font-size: 1rem;
        }
        .btn:hover {
            background-color: #166fe5;
        }
        .username-container {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #ffffff;
            min-width: 180px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 6px;
            overflow: hidden;
            transition: all 0.3s ease;
            right: 0;
        }
        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.2s ease;
            font-size: 0.9rem;
        }
        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        .username-container:hover .dropdown-content {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .profile-button {
            background-color: #21b0aa;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .profile-button:hover {
            background-color: #1a8f8a;
        }
        .profile-button i {
            font-size: 1.2rem;
        }
        .scroll-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(128, 128, 128, 0.7); /* Changed to grey */
            color: #ffffff; /* White arrow icon */
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: background-color 0.3s ease;
        }

        .scroll-arrow:hover {
            background-color: rgba(100, 100, 100, 0.9); /* Darker grey on hover */
        }

        .scroll-left {
            left: 10px;
        }

        .scroll-right {
            right: 10px;
        }

        .plant-container {
            position: relative;
            overflow: hidden;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .know-more-btn {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: block;
            font-size: 16px;
            margin: 20px auto;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
            width: fit-content;
        }

        .know-more-btn:hover {
            background-color: #45a049;
        }

        .modal-image {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .loading {
            text-align: center;
            margin-top: 20px;
        }

        .additional-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        /* Color Blind Friendly Styles */
        body.color-blind-friendly {
            /* Example adjustments */
            background-color: #f0f0f0;
            color: #000000;
        }
        body.color-blind-friendly .btn {
            background-color: #555555;
        }
        /* Add more styles as needed */
    </style>
</head>
<body>
    <div class="background-container"></div>
    <div class="dashboard-container">
        <div class="username-container">
            <button class="profile-button">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['name']); ?>
            </button>
            <div class="dropdown-content">
                <a href="plant_identifier.html"><i class="fas fa-leaf"></i> Identify New Plant</a>
                <a href="profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a>
                <a href="#" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
        
        <div class="header">
            <h1>Welcome to Dashboard</h1>
        </div>
        
        <div class="section">
            <h2>Account Statistics</h2>
            <div class="stats">
                <div class="stat-item">
                    <h3>Total Identifications</h3>
                    <p><?php echo $stats['total_identifications']; ?></p>
                </div>
                <div class="stat-item">
                    <h3>Active Days</h3>
                    <p><?php echo $stats['active_days']; ?></p>
                </div>
            </div>
        </div>

        <?php foreach (['Herbs', 'Shrubs', 'Trees'] as $category): ?>
            <div class="section">
                <h2><?php echo $category; ?></h2>
                <?php if (!empty($plants[$category])): ?>
                    <div class="plant-container">
                        <button class="scroll-arrow scroll-left" data-category="<?php echo $category; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="plant-list" id="<?php echo strtolower($category); ?>-list">
                            <?php foreach ($plants[$category] as $identification): ?>
                                <div class="plant-item">
                                    <img src="<?php echo htmlspecialchars($identification['image_path']); ?>" alt="Identified Plant">
                                    <h3><?php echo htmlspecialchars($identification['plant_name']); ?></h3>
                                    <p><em><?php echo htmlspecialchars($identification['scientific_name']); ?></em></p>
                                    <p><?php echo htmlspecialchars(substr($identification['description'], 0, 100)) . '...'; ?></p>
                                    <p>Identified on: <?php echo htmlspecialchars($identification['identification_date']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button class="scroll-arrow scroll-right" data-category="<?php echo $category; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                <?php else: ?>
                    <p>No <?php echo strtolower($category); ?> identified yet.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <a href="plant_identifier.html" class="btn">Identify New Plant</a>

        <!-- Color Blind Toggle Button -->
        <button id="color-toggle" class="btn" style="position: fixed; bottom: 20px; right: 20px;">
            Enable Color Blind Mode
        </button>
    </div>

    <div id="plantModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <img id="modalImage" src="" alt="Plant Image" class="modal-image">
            <h2 id="modalPlantName"></h2>
            <p id="modalScientificName"></p>
            <p id="modalDescription"></p>
            <p id="modalIdentificationDate"></p>
            <button id="knowMoreBtn" class="know-more-btn">Know More About this Plant</button>
            <div id="additionalInfo" class="additional-info" style="display: none;"></div>
            <div id="loading" class="loading" style="display: none;">Loading more information...</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scrollAmount = 220; // Adjust this value based on your plant item width + gap

            document.querySelectorAll('.scroll-arrow').forEach(arrow => {
                arrow.addEventListener('click', function() {
                    const category = this.dataset.category.toLowerCase();
                    const container = document.getElementById(`${category}-list`);
                    const scrollLeft = this.classList.contains('scroll-left') ? -scrollAmount : scrollAmount;
                    container.scrollBy({ left: scrollLeft, behavior: 'smooth' });
                });
            });

            document.getElementById('logout-link').addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    fetch('logout.php', {
                        method: 'POST',
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            alert('Logout failed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred during logout.');
                    });
                }
            });

            const modal = document.getElementById('plantModal');
            const closeBtn = document.getElementsByClassName('close')[0];
            const knowMoreBtn = document.getElementById('knowMoreBtn');
            const additionalInfo = document.getElementById('additionalInfo');
            const loading = document.getElementById('loading');

            document.querySelectorAll('.plant-item').forEach(item => {
                item.addEventListener('click', function() {
                    const plantName = this.querySelector('h3').textContent;
                    const scientificName = this.querySelector('em').textContent;
                    const description = this.querySelector('p:nth-of-type(2)').textContent;
                    const identificationDate = this.querySelector('p:last-child').textContent;
                    const imageSrc = this.querySelector('img').src;

                    document.getElementById('modalPlantName').textContent = plantName;
                    document.getElementById('modalScientificName').textContent = scientificName;
                    document.getElementById('modalDescription').textContent = description;
                    document.getElementById('modalIdentificationDate').textContent = identificationDate;
                    document.getElementById('modalImage').src = imageSrc;

                    additionalInfo.style.display = 'none';
                    additionalInfo.textContent = '';
                    modal.style.display = 'block';
                });
            });

            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }

            knowMoreBtn.onclick = function() {
                const plantName = document.getElementById('modalPlantName').textContent;
                loading.style.display = 'block';
                additionalInfo.style.display = 'none';

                fetch('identify.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=know_more&plant_name=' + encodeURIComponent(plantName)
                })
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    additionalInfo.textContent = data.result;
                    additionalInfo.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    loading.style.display = 'none';
                    additionalInfo.textContent = 'An error occurred while fetching additional information.';
                    additionalInfo.style.display = 'block';
                });
            }

            // Color Blind Toggle Script
            const toggleButton = document.getElementById('color-toggle');
            
            // Initialize toggle state from localStorage
            if (localStorage.getItem('colorBlindMode') === 'enabled') {
                document.body.classList.add('color-blind-friendly');
                toggleButton.textContent = 'Disable Color Blind Mode';
            }

            toggleButton.addEventListener('click', () => {
                document.body.classList.toggle('color-blind-friendly');
                if (document.body.classList.contains('color-blind-friendly')) {
                    toggleButton.textContent = 'Disable Color Blind Mode';
                    localStorage.setItem('colorBlindMode', 'enabled');
                } else {
                    toggleButton.textContent = 'Enable Color Blind Mode';
                    localStorage.setItem('colorBlindMode', 'disabled');
                }
            });
        });
    </script>
</body>
</html>