<?php
session_start();

function fetch_game_board() //from url
{
    $url = 'https://www.cs.uky.edu/~soward/CS316/generateBoard';
    $serialized_board = file_get_contents($url);
    return unserialize($serialized_board);
}

function reset_game()
{
    if (isset($_GET['use_custom_board'])) 
    {
        $_SESSION['game_board'] = generate_custom_board();
    }
    else 
    {
        $_SESSION['game_board'] = fetch_game_board();
    }

    $_SESSION['revealed'] = [];
    $_SESSION['ship_parts'] = [];
    $_SESSION['ship_sunk'] = [];
    $_SESSION['discoveries'] = 0;
    $_SESSION['unsuccessful_searches'] = 0;
    $_SESSION['total_searches'] = 0;
    $_SESSION['debug'] = false;

    
    $_SESSION['ship_counts'] = 
    [
        'S' => 3,  //submarine 3 parts
        'T' => 5,  //tanker 5 parts
        'L' => 4,  //liner 4 parts
        'Y' => 3,  //yacht 3 parts
        'C' => 2   //cruiser 2 parts
    ];
}
//extra credit
function generate_custom_board()
{
    //fill board with 10x10 zeros (water)
    $board = array_fill(0, 10, array_fill(0, 10, 0));

    $ships = 
    [
        'S' => 3,  // Submarine has 3 parts
        'T' => 5,  // Tanker has 5 parts
        'L' => 4,  // Liner has 4 parts
        'Y' => 3,  // Yacht has 3 parts
        'C' => 2   // Cruiser has 2 parts
    ];

    foreach ($ships as $ship => $size)
    {
        $placed = false;

        while (!$placed) 
        {
            $direction = rand(0, 1);
            $row = rand(0, 9);
            $col = rand(0, 9);

            if ($direction === 0 && $col + $size <= 10)
            {
                if (is_free_space($board, $row, $col, $size, $direction))
                {
                    for ($i = 0; $i < $size; $i++) 
                    {
                        $board[$row][$col + $i] = $ship;
                    }
                    $placed = true;
                }
            }
            elseif ($direction === 1 && $row + $size <= 10) 
            {
                if (is_free_space($board, $row, $col, $size, $direction))
                {
                    for ($i = 0; $i < $size; $i++) 
                    {
                        $board[$row + $i][$col] = $ship;
                    }
                    $placed = true;
                }
            }
        }
    }
    return $board;
}

//check free spaces to prevent overlap on custom board
function is_free_space($board, $row, $col, $size, $direction)
{
    for ($i = 0; $i < $size; $i++) 
    {
        if ($direction === 0 && $board[$row][$col + $i] !== 0) //horizontal
        {
            return false;
        }
        if ($direction === 1 && $board[$row + $i][$col] !== 0)//vertical
        {
            return false;
        }
    }
    return true;
}

//reset game
if (!isset($_SESSION['game_board']) || isset($_GET['new_game']) || isset($_GET['use_custom_board'])) 
{
    reset_game();
}

//debug button toggle
if (isset($_GET['toggleDebug'])) 
{
    $_SESSION['debug'] = !$_SESSION['debug'];
}

if (isset($_GET['row']) && isset($_GET['col'])) 
{
    $row = $_GET['row'];
    $col = $_GET['col'];

    $square = $_SESSION['game_board'][$row][$col];

    if (!isset($_SESSION['revealed'][$row][$col])) 
    {
        $_SESSION['total_searches']++;

        if ($square === 0)//miss if click water
        {
            $_SESSION['revealed'][$row][$col] = 'miss';
            $_SESSION['unsuccessful_searches']++;
        } 
        else 
        {
            $_SESSION['revealed'][$row][$col] = 'hit';
            $_SESSION['discoveries']++;

            if (!isset($_SESSION['ship_parts'][$square])) 
            {
                $_SESSION['ship_parts'][$square] = 0;
            }
            $_SESSION['ship_parts'][$square]++;

            $total_parts_for_ship = $_SESSION['ship_counts'][$square];

            //sink if hit all parts
            if ($_SESSION['ship_parts'][$square] >= $total_parts_for_ship) 
            {
                $_SESSION['ship_sunk'][$square] = true;
            }
        }
    }
}

//stats
$total_searches = $_SESSION['total_searches'];
$discoveries = $_SESSION['discoveries'];
$unsuccessful_searches = $_SESSION['unsuccessful_searches'];
$remaining_green_spots = 17 - $discoveries;
$search_accuracy = ($total_searches > 0) ? round(($discoveries / $total_searches) * 100, 2) : 0;
$areas_yet_to_search = 100 - $total_searches;
$odds_successful_search = ($discoveries > 0) ? round(($discoveries / 17) * 100, 2) : 0;

//if statement prevents dividing by zero
if ($areas_yet_to_search > 0) 
{
    $odds_successful_search = ($areas_yet_to_search > 0) ? round(($remaining_green_spots / $areas_yet_to_search) * 100, 2) : 0;
} 
else 
{
    $odds_successful_search = 0;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.3/dist/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/semantic-ui@2.5.0/dist/semantic.min.css">
    <script src="https://cdn.jsdelivr.net/npm/semantic-ui@2.5.0/dist/semantic.min.js"></script>
    <title>Naval Salvage!</title>
    <style>
        div.ui.segment.battle td
        {
            margin: 2px;
            width: 64px;
            height: 64px;
            padding: 0px;
        }

        .unsink 
        {
            animation: spin 7s ease-in-out 2s infinite;
            animation-iteration-count: 10;
            animation-timing-function: cubic-bezier(.9, .5, .5, .9);
            transform-origin: top;
            transform: translate(0%, 850%) rotate(180deg);
            font-weight: 500;
        }

        @keyframes spin 
        {
            50% 
            {
                transform-origin: top;
                transform: translate(0%, 0%) rotate(0deg);
            }
        }

        div.hit 
        {
            height: 64px;
            width: 64px;
            background-image: url("hit.png");
            background-color: green;
            background-position: center;
            background-repeat: no-repeat;
            background-size: 80%;
        }

        div.water 
        {
            height: 64px;
            width: 64px;
            background-image: url("water.png");
            background-size: contain;
        }

        div.miss
        {
            height: 64px;
            width: 64px;
            background-image: url("miss.png");
            background-position: center;
            background-repeat: no-repeat;
            background-color: red;
            background-size: 80%;

        }

        div.unsank 
        {
            height: 64px;
            width: 64px;
            background-position: center;
            background-repeat: no-repeat;
            background-color: yellow;
            background-size: 80%;
        }

        div.unsank.T 
        {
            background-image: url('tanker.png');
        }

        div.unsank.L
        {
            background-image: url('liner.png');
        }

        div.unsank.S
        {
            background-image: url('submarine.png');
        }

        div.unsank.Y 
        {
            background-image: url('yacht.png');
        }

        div.unsank.C 
        {
            background-image: url('cruiser.png');
        }

        body 
        {
            width: 95%;
            margin: auto;
        }

        table.ships 
        {
            min-width: 400px;
            text-align: center;
            border-collapse: collapse;
            background-color: transparent;
        }

        .ships th, .ships td 
        {
            font-size: 18px;
            font-weight: bold;
            padding: 10px;
            border: none;
        }

        .ships img 
        {
            width: 64px;
            height: 64px;
            object-fit: cover;
        }

        table.stats 
        {
            width: 100%;
            text-align: center;
        }

        .stats td:first-child
        {
            text-align: center;
            font-weight: bold;
        }

        .stats td:nth-child(2)
        {
            text-align: left;
        }

        .stats tr:nth-child(even) 
        {
            background-color: lightblue;
        }

        .bigLines::first-letter 
        {
            font-size: 200%;
            font-weight: 1000;
        }

    </style>
</head>

<body>
    <!-- side icon -->
    <div class="ui grid">
        <div class="two wide column">
            <div class="ui icon header unsink">
                <i class="ship icon"></i>
                <div class="content">Naval Salvage!</div>
            </div>
        </div>

        <!-- game board -->
        <div class="nine wide column">
            <div class="ui segment battle">
                <table>
                    <?php
                    for ($i = 0; $i < 10; $i++) 
                    {
                        echo '<tr>';
                        for ($j = 0; $j < 10; $j++)
                        {
                            $cell_state = isset($_SESSION['revealed'][$i][$j]) ? $_SESSION['revealed'][$i][$j] : 'water';
                            $square_value = $_SESSION['game_board'][$i][$j];
                            $class = 'water';

                            if ($cell_state == 'hit')
                            {
                                if (isset($_SESSION['ship_sunk'][$square_value])) 
                                {
                                    switch ($square_value)
                                    {
                                        case 'S':
                                            $class = 'unsank S';
                                            break;
                                        case 'T':
                                            $class = 'unsank T';
                                            break;
                                        case 'L':
                                            $class = 'unsank L';
                                            break;
                                        case 'Y':
                                            $class = 'unsank Y';
                                            break;
                                        case 'C':
                                            $class = 'unsank C';
                                            break;
                                    }
                                } 
                                else 
                                {
                                    $class = 'hit';
                                }
                            }
                            elseif ($cell_state == 'miss') 
                            {
                                $class = 'miss';
                            }

                            if ($_SESSION['debug']) 
                            {
                                echo "<td><a href='?row=$i&col=$j'><div class='$class' style='color: black;'>$square_value</div></a></td>";
                            } 
                            else 
                            {
                                echo "<td><a href='?row=$i&col=$j'><div class='$class'></div></a></td>";
                            }
                        }
                        echo '</tr>';
                    }
                    ?>
                </table>
            </div>
        </div>

        <!-- game rules -->
        <div class="five wide column">
            <div class="ui vertically stacked segments">
                <div class="ui segment about">
                    <div class="ui top attached label huge red">How to play</div>
                    <br><br>
                    <p class="bigLines">
                        <strong>Explore the ocean for treasures.</strong>
                        <ul>
                            <li>Send your probe down any open ocean square (blue) by clicking on it.</li>
                            <li>If it turns red, you found nothing...</li>
                            <li>If it turns green, you found part of a wreck.</li>
                            <li>Find all the parts of a wreck to salvage the ship (it'll turn yellow).</li>
                            <li>Find all 5 of the ships listed below to win, note their different lengths.</li>
                        </ul>
                        <strong><em>Track your stats across games!</em></strong>
                    </p>
                </div>

                <!-- icons and ship types -->
                <div class="ui segment key">
                    <div class="ui top attached label huge red">Ships to Salvage (1 of each type)</div>
                    <br><br>
                    <table class="ships">
                        <thead>
                            <tr>
                                <th>Size</th>
                                <th>Type</th>
                                <th>Icon</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>5</td>
                                <td>Tanker</td>
                                <td><img src="tanker.png"></td>
                            </tr>
                            <tr>
                                <td>4</td>
                                <td>Liner</td>
                                <td><img src="liner.png"></td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Submarine</td>
                                <td><img src="submarine.png"></td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Yacht</td>
                                <td><img src="yacht.png"></td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Cruiser</td>
                                <td><img src="cruiser.png"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- stats table -->
                <div class="ui segment">
                    <div class="ui top attached label red huge stats">Stats</div>
                    <br><br>
                    <table class="stats">
                        <tr>
                            <td>Discoveries</td>
                            <td><?php echo $discoveries; ?> of 17</td>
                        </tr>
                        <tr>
                            <td>Unsuccessful Searches</td>
                            <td><?php echo $unsuccessful_searches; ?></td>
                        </tr>
                        <tr>
                            <td>Search Accuracy</td>
                            <td><?php echo $search_accuracy; ?>%</td>
                        </tr>
                        <tr>
                            <td>Areas Yet to Search</td>
                            <td><?php echo $areas_yet_to_search; ?></td>
                        </tr>
                        <tr>
                            <td>Odds of a Successful Search</td>
                            <td><?php echo $odds_successful_search; ?>%</td>
                        </tr>
                    </table>
                </div>

                <!-- buttons -->
                <div class="ui segment">
                    <div class="ui top attached label huge controls red">Controls</div>
                    <br><br><br>
                    <a href="?new_game=1"><div class="ui button">New Game (from URL)</div></a>
                    <a href="?use_custom_board=1"><div class="ui button">New Game (Custom Board)</div></a>
                    <a href="?toggleDebug=1"><div class="ui toggle button">Enable Debug</div></a>
                    <br>
                </div>
            </div>
        </div>
    </div>
</body>
</html>