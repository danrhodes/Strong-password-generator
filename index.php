<?php
session_start();

function generatePassword($words, $excludedWords) {
    do {
        $word1 = $words[array_rand($words)];
    } while (in_array(strtolower($word1), $excludedWords));
    do {
        $word2 = $words[array_rand($words)];
    } while (in_array(strtolower($word2), $excludedWords) || $word2 === $word1);
    
    $number = str_pad(rand(10, 99), 2, '0', STR_PAD_LEFT);
    return $word1 . $word2 . $number . '!';
}

function calculatePasswordStrength($password) {
    $length = strlen($password);
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasDigits = preg_match('/\d/', $password);
    $hasSpecialChars = preg_match('/[^A-Za-z0-9]/', $password);

    $strength = 0;
    $strength += $length * 4;
    $strength += ($hasUppercase ? 1 : 0) * 10;
    $strength += ($hasLowercase ? 1 : 0) * 10;
    $strength += ($hasDigits ? 1 : 0) * 10;
    $strength += ($hasSpecialChars ? 1 : 0) * 15;

    if ($strength < 50) return ['score' => 1, 'label' => 'Weak', 'color' => 'red'];
    if ($strength < 75) return ['score' => 2, 'label' => 'Fair', 'color' => 'orange'];
    if ($strength < 100) return ['score' => 3, 'label' => 'Good', 'color' => 'yellow'];
    return ['score' => 4, 'label' => 'Strong', 'color' => 'green'];
}

$words = [
    'Apple', 'Banana', 'Orange', 'Grape', 'Kiwi', 'Lemon', 'Pear', 'Cherry', 'Mango',
    'Chair', 'Table', 'Bottle', 'Window', 'Pillow', 'Cup', 'Phone', 'Clock', 'Lamp',
    'Book', 'Pen', 'Pencil', 'Bag', 'Bread', 'Cheese', 'Tomato', 'Onion', 'Potato',
    'Carrot', 'Egg', 'Milk', 'Sugar', 'Salt', 'Rice', 'Pasta', 'Coffee', 'Tea', 'Juice',
    'Water', 'Glass', 'Plate', 'Bowl', 'Soap', 'Towel', 'Socks', 'Shirt', 'Pants', 'Hat',
    'Door', 'Car', 'Bike', 'Tree', 'Flower', 'Grass', 'Leaf', 'Rock', 'Cloud', 'Sun',
    'Moon', 'Star', 'Bird', 'Dog', 'Cat', 'Fish', 'Desk', 'Chair', 'Bed', 'Sofa', 'Rug',
    'Curtain', 'Paint', 'Brush', 'Toy', 'Game', 'Ball', 'Doll', 'Truck', 'Boat', 'Plane',
    'Train', 'Bus', 'Taxi', 'Park', 'Beach', 'Lake', 'River', 'Ocean', 'Mountain', 'Hill',
    'Valley', 'Forest', 'Desert', 'Island', 'Cave', 'Bridge', 'Road', 'Street', 'House',
    'School', 'Store', 'Market', 'Bank', 'Hotel', 'Fire', 'Earth', 'Wind', 'Rain', 'Snow'
];

$passwords = [];
$message = '';
$strengthDistribution = [0, 0, 0, 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {
        $count = intval($_POST['password_count']);
        $excludeWords = array_map('trim', explode(',', strtolower($_POST['exclude_words'])));
        $showOnlyStrong = isset($_POST['show_only_strong']);
        
        for ($i = 0; $i < $count; $i++) {
            $password = generatePassword($words, $excludeWords);
            $strength = calculatePasswordStrength($password);
            if (!$showOnlyStrong || $strength['score'] == 4) {
                $passwords[] = ['password' => $password, 'strength' => $strength];
            }
            $strengthDistribution[$strength['score'] - 1]++;
        }
        
        $_SESSION['generated_passwords'] = $passwords;
        $_SESSION['strength_distribution'] = $strengthDistribution;
    } elseif (isset($_POST['export_csv'])) {
        if (isset($_SESSION['generated_passwords'])) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="generated_passwords.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Password', 'Strength']);
            foreach ($_SESSION['generated_passwords'] as $passwordData) {
                fputcsv($output, [$passwordData['password'], $passwordData['strength']['label']]);
            }
            fclose($output);
            exit;
        } else {
            $message = 'No passwords generated yet. Generate passwords before exporting.';
        }
    } elseif (isset($_POST['copy_password'])) {
        $copiedPassword = $_POST['copy_password'];
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!isset($_SESSION['copied_passwords'])) {
            $_SESSION['copied_passwords'] = [];
        }
        $_SESSION['copied_passwords'][] = [
            'password' => $copiedPassword,
            'timestamp' => $timestamp,
            'ip' => $ip
        ];
        
        echo json_encode(['success' => true]);
        exit;
    } elseif (isset($_POST['delete_password'])) {
        $index = intval($_POST['delete_password']);
        if (isset($_SESSION['copied_passwords'][$index])) {
            unset($_SESSION['copied_passwords'][$index]);
            $_SESSION['copied_passwords'] = array_values($_SESSION['copied_passwords']);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strong Password Generator</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .password-item {
            transition: all 0.3s ease;
        }
        .password-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(255, 255, 255, 0.1);
        }
        .custom-range {
            -webkit-appearance: none;
            width: 100%;
            height: 10px;
            border-radius: 5px;
            background: #4a5568;
            outline: none;
        }
        .custom-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #63b3ed;
            cursor: pointer;
        }
        .custom-range::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #63b3ed;
            cursor: pointer;
        }
        .strength-meter {
            width: 100px;
            height: 10px;
            background-color: #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }
        .strength-meter-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        #chart-container {
            height: 150px;
        }
        @media (max-width: 640px) {
            .password-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .password-item > div {
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex flex-col items-center justify-center p-4 text-gray-300">
    <div class="bg-gray-800 rounded-lg shadow-lg p-6 w-full max-w-2xl">
        <h1 class="text-3xl font-bold text-center text-blue-400 mb-6">Strong Password Generator</h1>
        
        <form method="POST" action="">
            <div class="mb-6">
                <label for="password-count" class="block text-sm font-medium mb-2">
                    Number of Passwords: <span id="slider-value" class="font-bold">5</span>
                </label>
                <input type="range" id="password-count" name="password_count" min="1" max="1000" value="5"
                       class="custom-range">
            </div>
            
            <div class="mb-4">
                <label for="exclude-words" class="block text-sm font-medium mb-2">Exclude Words (comma-separated):</label>
                <input type="text" id="exclude-words" name="exclude_words" class="w-full bg-gray-700 text-white rounded px-3 py-2" placeholder="e.g., Apple, Banana, Orange">
            </div>

            <div class="mb-4">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="show_only_strong" class="form-checkbox text-blue-600">
                    <span class="ml-2">Show only strong passwords</span>
                </label>
            </div>
            
            <button type="submit" name="generate" id="generate-btn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-300 ease-in-out transform hover:scale-105 mb-4">
                Generate Passwords
            </button>
        </form>
        
        <form method="POST" action="">
            <button type="submit" name="export_csv"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300 ease-in-out transform hover:scale-105 mb-6">
                Export to CSV
            </button>
        </form>
        
        <div class="mt-4 mb-6">
            <h2 class="text-xl font-bold mb-2">Password Strength Distribution</h2>
            <div id="chart-container">
                <canvas id="strengthChart"></canvas>
            </div>
        </div>

        <div class="bg-gray-700 border border-gray-600 rounded p-4 mb-6">
    <h3 class="text-lg font-bold mb-2">Keyboard Shortcuts</h3>
    <ul class="list-disc list-inside">
        <li><kbd class="bg-gray-600 text-white px-2 py-1 rounded">Ctrl + G</kbd> Generate new passwords</li>
        <li><kbd class="bg-gray-600 text-white px-2 py-1 rounded">Ctrl + Alt + C</kbd> Copy the last generated password</li>
    </ul>
</div>
        
        <?php if (!empty($message)): ?>
            <div class="bg-red-500 text-white p-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <ul id="password-list" class="mt-6 space-y-2">
            <?php foreach ($passwords as $passwordData): ?>
                <li class="password-item bg-gray-700 border border-gray-600 rounded p-3 flex justify-between items-center">
                    <span class="font-mono text-lg"><?php echo htmlspecialchars($passwordData['password']); ?></span>
                    <div class="flex items-center space-x-2">
                        <div class="strength-meter">
                            <div class="strength-meter-fill bg-<?php echo $passwordData['strength']['color']; ?>-500" style="width: <?php echo $passwordData['strength']['score'] * 25; ?>%;"></div>
                        </div>
                        <span class="text-sm text-<?php echo $passwordData['strength']['color']; ?>-500"><?php echo $passwordData['strength']['label']; ?></span>
                        <button class="copy-btn text-blue-400 hover:text-blue-300 focus:outline-none" data-password="<?php echo htmlspecialchars($passwordData['password']); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/>
                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <div id="copied-passwords" class="mt-8">
    <h2 class="text-xl font-bold mb-4">Copied Passwords</h2>
    <ul id="copied-password-list" class="space-y-2">
        <?php if (isset($_SESSION['copied_passwords'])): ?>
            <?php foreach ($_SESSION['copied_passwords'] as $index => $info): ?>
                <li class="bg-gray-700 border border-gray-600 rounded p-3 flex justify-between items-center">
                    <span><?php echo htmlspecialchars("{$info['password']} - Copied on {$info['timestamp']} from IP: {$info['ip']}"); ?></span>
                    <div>
                        <button class="copy-saved-btn text-blue-400 hover:text-blue-300 focus:outline-none mr-2" data-password="<?php echo htmlspecialchars($info['password']); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/>
                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/>
                            </svg>
                        </button>
                        <button class="delete-btn text-red-500 hover:text-red-400 focus:outline-none" data-index="<?php echo $index; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>

    <script>
        const passwordCountSlider = document.getElementById('password-count');
        const sliderValue = document.getElementById('slider-value');

        passwordCountSlider.addEventListener('input', function () {
            sliderValue.textContent = this.value;
        });

        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const password = this.dataset.password;
                navigator.clipboard.writeText(password).then(() => {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<span class="text-green-500">Copied!</span>';
                    setTimeout(() => {
                        this.innerHTML = originalText;
                    }, 2000);

                    // Track copied password
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'copy_password=' + encodeURIComponent(password)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
                });
            });
        });

        document.querySelectorAll('.copy-saved-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const password = this.dataset.password;
        navigator.clipboard.writeText(password).then(() => {
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="text-green-500">Copied!</span>';
            setTimeout(() => {
                this.innerHTML = originalHTML;
            }, 2000);
        });
    });
});

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.dataset.index;
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'delete_password=' + encodeURIComponent(index)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.ctrlKey && event.key === 'g') {
                event.preventDefault();
                document.getElementById('generate-btn').click();
            } else if (event.ctrlKey && event.altKey && event.key === 'c') {
                event.preventDefault();
                const lastPassword = document.querySelector('#password-list li:last-child .copy-btn');
                if (lastPassword) {
                    lastPassword.click();
                }
            }
        });

        // Strength distribution chart
        const ctx = document.getElementById('strengthChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Weak', 'Fair', 'Good', 'Strong'],
                datasets: [{
                    label: 'Password Strength',
                    data: <?php echo json_encode($_SESSION['strength_distribution'] ?? [0, 0, 0, 0]); ?>,
                    backgroundColor: ['#EF4444', '#F59E0B', '#EAB308', '#22C55E']
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    title: {
                        display: false,
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            color: '#D1D5DB'
                        },
                        grid: {
                            color: '#4B5563'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#D1D5DB'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>