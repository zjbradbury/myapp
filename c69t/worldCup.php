<?php
require_once "config.php";

$apiKey = "5db2245321e542a88845416c09088cc4";
$competitionCode = "WC";
$season = "2026";

$selfPage = basename($_SERVER["PHP_SELF"]);
$canEdit = function_exists("isLoggedIn") && function_exists("currentRole") && isLoggedIn() && currentRole() === "admin";

$stageRank = [
    "Winner" => 100,
    "Runner Up" => 95,
    "Third Place" => 90,
    "Fourth Place" => 85,
    "Semi Final" => 80,
    "Quarter Final" => 70,
    "Round of 16" => 60,
    "Round of 32" => 50,
    "Group Stage" => 10
];

if (!function_exists("h")) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists("money")) {
    function money($v) {
        return "$" . number_format((float)$v, 2);
    }
}

function redirectSelf($msg) {
    global $selfPage;
    header("Location: {$selfPage}?msg=" . urlencode($msg));
    exit;
}

function upsertTeam(PDO $pdo, array $team): ?int {
    if (empty($team["id"]) || empty($team["name"])) return null;

    $stmt = $pdo->prepare("SELECT id FROM sweep_teams WHERE api_team_id = ?");
    $stmt->execute([$team["id"]]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE sweep_teams
            SET team_name = ?, short_name = ?, flag_url = ?
            WHERE api_team_id = ?
        ");
        $stmt->execute([
            $team["name"],
            $team["shortName"] ?? $team["name"],
            $team["crest"] ?? null,
            $team["id"]
        ]);
        return (int)$existing;
    }

    $stmt = $pdo->prepare("
        INSERT INTO sweep_teams
        (api_team_id, team_name, short_name, flag_url, stage_reached, eliminated)
        VALUES (?, ?, ?, ?, 'Group Stage', 0)
    ");
    $stmt->execute([
        $team["id"],
        $team["name"],
        $team["shortName"] ?? $team["name"],
        $team["crest"] ?? null
    ]);

    return (int)$pdo->lastInsertId();
}

function setStage(array &$stages, int $teamId, string $stage, bool $eliminated) {
    $rank = [
        "Winner" => 100,
        "Runner Up" => 95,
        "Third Place" => 90,
        "Fourth Place" => 85,
        "Semi Final" => 80,
        "Quarter Final" => 70,
        "Round of 16" => 60,
        "Round of 32" => 50,
        "Group Stage" => 10
    ];

    if (!isset($stages[$teamId])) {
        $stages[$teamId] = ["stage" => "Group Stage", "eliminated" => 0];
    }

    $currentRank = $rank[$stages[$teamId]["stage"]] ?? 0;
    $newRank = $rank[$stage] ?? 0;

    if ($newRank >= $currentRank) {
        $stages[$teamId] = [
            "stage" => $stage,
            "eliminated" => $eliminated ? 1 : 0
        ];
    }
}

function autoCalculateTeamStages(PDO $pdo) {
    $teams = $pdo->query("SELECT id FROM sweep_teams")->fetchAll(PDO::FETCH_COLUMN);

    $stages = [];
    foreach ($teams as $id) {
        $stages[(int)$id] = [
            "stage" => "Group Stage",
            "eliminated" => 0
        ];
    }

    $matches = $pdo->query("
        SELECT *
        FROM sweep_matches
        WHERE completed = 1
          AND team1_score IS NOT NULL
          AND team2_score IS NOT NULL
        ORDER BY match_date, match_time, id
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($matches as $m) {
        $stageRaw = strtoupper((string)$m["stage"]);
        $team1 = (int)$m["team1_id"];
        $team2 = (int)$m["team2_id"];
        $winner = (int)$m["winner_team_id"];

        if (!$winner) continue;

        $loser = ($winner === $team1) ? $team2 : $team1;

        if (str_contains($stageRaw, "FINAL") && !str_contains($stageRaw, "SEMI") && !str_contains($stageRaw, "THIRD")) {
            setStage($stages, $winner, "Winner", false);
            setStage($stages, $loser, "Runner Up", true);
        } elseif (str_contains($stageRaw, "THIRD")) {
            setStage($stages, $winner, "Third Place", true);
            setStage($stages, $loser, "Fourth Place", true);
        } elseif (str_contains($stageRaw, "SEMI")) {
            setStage($stages, $winner, "Semi Final", false);
            setStage($stages, $loser, "Semi Final", true);
        } elseif (str_contains($stageRaw, "QUARTER")) {
            setStage($stages, $winner, "Quarter Final", false);
            setStage($stages, $loser, "Quarter Final", true);
        } elseif (
            str_contains($stageRaw, "LAST_16") ||
            str_contains($stageRaw, "ROUND_OF_16") ||
            str_contains($stageRaw, "ROUND OF 16")
        ) {
            setStage($stages, $winner, "Round of 16", false);
            setStage($stages, $loser, "Round of 16", true);
        } elseif (
            str_contains($stageRaw, "LAST_32") ||
            str_contains($stageRaw, "ROUND_OF_32") ||
            str_contains($stageRaw, "ROUND OF 32")
        ) {
            setStage($stages, $winner, "Round of 32", false);
            setStage($stages, $loser, "Round of 32", true);
        }
    }

    $stmt = $pdo->prepare("
        UPDATE sweep_teams
        SET stage_reached = ?, eliminated = ?
        WHERE id = ?
    ");

    foreach ($stages as $teamId => $data) {
        $stmt->execute([
            $data["stage"],
            $data["eliminated"],
            $teamId
        ]);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$canEdit) {
        http_response_code(403);
        die("Access denied. Admin login required.");
    }

    $action = $_POST["action"] ?? "";

    if ($action === "add_player") {
        $name = trim($_POST["name"] ?? "");
        $paid = (float)($_POST["paid"] ?? 0);

        if ($name !== "") {
            $stmt = $pdo->prepare("INSERT INTO sweep_players (name, paid) VALUES (?, ?)");
            $stmt->execute([$name, $paid]);
        }

        redirectSelf("Player added");
    }

    if ($action === "delete_player") {
        $playerId = (int)($_POST["player_id"] ?? 0);

        if ($playerId > 0) {
            $stmt = $pdo->prepare("UPDATE sweep_teams SET player_id = NULL WHERE player_id = ?");
            $stmt->execute([$playerId]);

            $stmt = $pdo->prepare("DELETE FROM sweep_players WHERE id = ?");
            $stmt->execute([$playerId]);
        }

        redirectSelf("Player deleted");
    }

    if ($action === "assign_team") {
        $stmt = $pdo->prepare("UPDATE sweep_teams SET player_id = ? WHERE id = ?");
        $stmt->execute([
            $_POST["player_id"] ?: null,
            $_POST["team_id"]
        ]);

        redirectSelf("Team owner updated");
    }

    if ($action === "save_payout") {
        $stmt = $pdo->prepare("UPDATE sweep_payouts SET amount = ? WHERE id = ?");
        $stmt->execute([
            $_POST["amount"] ?? 0,
            $_POST["payout_id"]
        ]);

        redirectSelf("Payout updated");
    }

    if ($action === "sync_results") {
        if ($apiKey === "" || $apiKey === "PASTE_FOOTBALL_DATA_API_KEY_HERE") {
            redirectSelf("API key missing");
        }

        $url = "https://api.football-data.org/v4/competitions/$competitionCode/matches?season=$season";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["X-Auth-Token: $apiKey"],
            CURLOPT_TIMEOUT => 25
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            redirectSelf("Sync failed. HTTP $httpCode $error");
        }

        $data = json_decode($response, true);

        if (!isset($data["matches"])) {
            redirectSelf("Sync failed. No matches returned.");
        }

        foreach ($data["matches"] as $match) {
            $homeId = upsertTeam($pdo, $match["homeTeam"]);
            $awayId = upsertTeam($pdo, $match["awayTeam"]);

            if (!$homeId || !$awayId) continue;

            $localDate = null;
            $localTime = null;

            if (!empty($match["utcDate"])) {
                $dt = new DateTime($match["utcDate"], new DateTimeZone("UTC"));
                $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
                $localDate = $dt->format("Y-m-d");
                $localTime = $dt->format("H:i:s");
            }

            $homeScore = $match["score"]["fullTime"]["home"] ?? null;
            $awayScore = $match["score"]["fullTime"]["away"] ?? null;

            $winnerLocalId = null;

            if (($match["score"]["winner"] ?? "") === "HOME_TEAM") {
                $winnerLocalId = $homeId;
            } elseif (($match["score"]["winner"] ?? "") === "AWAY_TEAM") {
                $winnerLocalId = $awayId;
            }

            $completed = in_array($match["status"] ?? "", ["FINISHED", "AWARDED"], true) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO sweep_matches
                (
                    api_match_id, match_date, match_time, stage,
                    team1_id, team2_id,
                    team1_score, team2_score,
                    winner_team_id, completed, status, last_synced
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    match_date = VALUES(match_date),
                    match_time = VALUES(match_time),
                    stage = VALUES(stage),
                    team1_id = VALUES(team1_id),
                    team2_id = VALUES(team2_id),
                    team1_score = VALUES(team1_score),
                    team2_score = VALUES(team2_score),
                    winner_team_id = VALUES(winner_team_id),
                    completed = VALUES(completed),
                    status = VALUES(status),
                    last_synced = NOW()
            ");

            $stmt->execute([
                $match["id"],
                $localDate,
                $localTime,
                $match["stage"] ?? "Group Stage",
                $homeId,
                $awayId,
                $homeScore,
                $awayScore,
                $winnerLocalId,
                $completed,
                $match["status"] ?? null
            ]);
        }

        autoCalculateTeamStages($pdo);

        redirectSelf("Synced successfully and placings updated");
    }
}

$msg = $_GET["msg"] ?? "";

$players = $pdo->query("SELECT * FROM sweep_players ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$payouts = $pdo->query("SELECT * FROM sweep_payouts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$teams = $pdo->query("
    SELECT 
        t.*,
        p.name AS player_name,

        COALESCE(SUM(
            CASE
                WHEN m.completed = 1 AND m.winner_team_id = t.id THEN 3
                WHEN m.completed = 1
                 AND m.winner_team_id IS NULL
                 AND m.team1_score IS NOT NULL
                 AND m.team2_score IS NOT NULL
                 AND m.team1_score = m.team2_score
                 AND (m.team1_id = t.id OR m.team2_id = t.id)
                THEN 1
                ELSE 0
            END
        ), 0) AS points,

        COALESCE(SUM(
            CASE
                WHEN m.completed = 1 AND m.team1_id = t.id THEN m.team1_score
                WHEN m.completed = 1 AND m.team2_id = t.id THEN m.team2_score
                ELSE 0
            END
        ), 0) AS goals_for,

        COALESCE(SUM(
            CASE
                WHEN m.completed = 1 AND m.team1_id = t.id THEN m.team2_score
                WHEN m.completed = 1 AND m.team2_id = t.id THEN m.team1_score
                ELSE 0
            END
        ), 0) AS goals_against,

        COALESCE(SUM(CASE WHEN m.completed = 1 THEN 1 ELSE 0 END), 0) AS played

    FROM sweep_teams t
    LEFT JOIN sweep_players p ON p.id = t.player_id
    LEFT JOIN sweep_matches m ON m.team1_id = t.id OR m.team2_id = t.id
    GROUP BY t.id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($teams as &$t) {
    $t["rank_score"] = $stageRank[$t["stage_reached"]] ?? 0;
    $t["goal_diff"] = (int)$t["goals_for"] - (int)$t["goals_against"];
}
unset($t);

usort($teams, function ($a, $b) {
    if ($b["rank_score"] !== $a["rank_score"]) return $b["rank_score"] <=> $a["rank_score"];
    if ($b["points"] !== $a["points"]) return $b["points"] <=> $a["points"];
    if ($b["goal_diff"] !== $a["goal_diff"]) return $b["goal_diff"] <=> $a["goal_diff"];
    if ($b["goals_for"] !== $a["goals_for"]) return $b["goals_for"] <=> $a["goals_for"];
    return strcmp($a["team_name"], $b["team_name"]);
});

$matches = $pdo->query("
    SELECT 
        m.*,
        t1.team_name AS team1,
        t2.team_name AS team2,
        t1.flag_url AS team1_flag,
        t2.flag_url AS team2_flag
    FROM sweep_matches m
    JOIN sweep_teams t1 ON t1.id = m.team1_id
    JOIN sweep_teams t2 ON t2.id = m.team2_id
    ORDER BY m.match_date, m.match_time, m.id
")->fetchAll(PDO::FETCH_ASSOC);

$lastSync = $pdo->query("SELECT MAX(last_synced) FROM sweep_matches")->fetchColumn();

$payoutMap = [];
foreach ($payouts as $p) {
    $payoutMap[$p["placing"]] = $p["amount"];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>World Cup Sweepstake</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e5e7eb;
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 24px;
        }

        h1, h2 {
            margin-top: 0;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 18px;
        }

        .card {
            background: #111827;
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 12px 28px rgba(0,0,0,.28);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            color: #93c5fd;
            text-align: left;
            font-size: 13px;
            padding: 9px;
            border-bottom: 1px solid rgba(255,255,255,.16);
            white-space: nowrap;
        }

        td {
            padding: 9px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            font-size: 14px;
        }

        input, select {
            background: #020617;
            color: white;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 8px;
            padding: 7px;
            max-width: 170px;
        }

        button, .button {
            background: #2563eb;
            color: white;
            border: 0;
            border-radius: 10px;
            padding: 9px 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-weight: bold;
        }

        button:hover, .button:hover {
            background: #1d4ed8;
        }

        .delete-btn {
            background: #dc2626;
        }

        .msg {
            background: #064e3b;
            border: 1px solid #10b981;
            color: #d1fae5;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .rank-1 {
            background: rgba(234,179,8,.22);
            font-weight: bold;
        }

        .rank-2 {
            background: rgba(203,213,225,.16);
        }

        .rank-3 {
            background: rgba(180,83,9,.18);
        }

        .muted {
            color: #94a3b8;
            font-size: 13px;
        }

        .flag {
            height: 20px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .admin-pill, .viewer-pill {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            display: inline-block;
            margin-left: 8px;
        }

        .admin-pill {
            background: #14532d;
            color: #bbf7d0;
        }

        .viewer-pill {
            background: #334155;
            color: #cbd5e1;
        }

        .winner-money {
            color: #22c55e;
            font-weight: bold;
        }

        .status-finished {
            color: #22c55e;
            font-weight: bold;
        }

        .status-timed {
            color: #60a5fa;
        }

        .status-scheduled {
            color: #facc15;
        }
    </style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div>
            <h1>
                World Cup Sweepstake
                <?php if ($canEdit): ?>
                    <span class="admin-pill">Admin edit mode</span>
                <?php else: ?>
                    <span class="viewer-pill">Public view</span>
                <?php endif; ?>
            </h1>
            <div class="muted">
                Last sync: <?= $lastSync ? h($lastSync) : "Never" ?>
            </div>
        </div>

        <div>
            <?php if ($canEdit): ?>
                <form method="post">
                    <input type="hidden" name="action" value="sync_results">
                    <button type="submit">Sync Online Results</button>
                </form>
            <?php else: ?>
                <a class="button" href="login.php?redirect=<?= urlencode($selfPage) ?>">Admin Login</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="msg"><?= h($msg) ?></div>
    <?php endif; ?>

    <div class="grid">

        <div class="card">
            <h2>Automatic Placings</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Team</th>
                        <th>Owner</th>
                        <th>Auto Stage</th>
                        <th>Pts</th>
                        <th>GD</th>
                        <th>Payout</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($teams as $i => $t): ?>
                    <?php
                        $placeLabel = "";
                        $payout = "";

                        if ($i === 0) {
                            $placeLabel = "Winner";
                            $payout = $payoutMap["Winner"] ?? 0;
                        } elseif ($i === 1) {
                            $placeLabel = "Runner Up";
                            $payout = $payoutMap["Runner Up"] ?? 0;
                        } elseif ($i === 2) {
                            $placeLabel = "Third Place";
                            $payout = $payoutMap["Third Place"] ?? 0;
                        } elseif ($i === 3) {
                            $placeLabel = "Fourth Place";
                            $payout = $payoutMap["Fourth Place"] ?? 0;
                        }
                    ?>
                    <tr class="rank-<?= $i + 1 ?>">
                        <td><?= $i + 1 ?></td>
                        <td>
                            <?php if (!empty($t["flag_url"])): ?>
                                <img class="flag" src="<?= h($t["flag_url"]) ?>">
                            <?php endif; ?>
                            <?= h($t["team_name"]) ?>
                        </td>
                        <td><?= h($t["player_name"] ?: "Unassigned") ?></td>
                        <td><?= h($t["stage_reached"]) ?></td>
                        <td><?= (int)$t["points"] ?></td>
                        <td><?= (int)$t["goal_diff"] ?></td>
                        <td>
                            <?php if ($placeLabel): ?>
                                <span class="winner-money"><?= h($placeLabel) ?> - <?= money($payout) ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Players</h2>

            <?php if ($canEdit): ?>
                <form method="post" style="margin-bottom:16px;">
                    <input type="hidden" name="action" value="add_player">
                    <input name="name" placeholder="Player name" required>
                    <input name="paid" type="number" step="0.01" placeholder="Paid amount">
                    <button>Add Player</button>
                </form>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Paid</th>
                        <?php if ($canEdit): ?><th>Delete</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($players as $p): ?>
                    <tr>
                        <td><?= h($p["name"]) ?></td>
                        <td><?= money($p["paid"]) ?></td>
                        <?php if ($canEdit): ?>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this player? Teams will become unassigned.');">
                                    <input type="hidden" name="action" value="delete_player">
                                    <input type="hidden" name="player_id" value="<?= h($p["id"]) ?>">
                                    <button class="delete-btn">Delete</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:24px;">Payouts</h2>

            <table>
                <thead>
                    <tr>
                        <th>Placing</th>
                        <th>Amount</th>
                        <?php if ($canEdit): ?><th>Save</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payouts as $p): ?>
                    <tr>
                        <?php if ($canEdit): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="save_payout">
                                <input type="hidden" name="payout_id" value="<?= h($p["id"]) ?>">
                                <td><?= h($p["placing"]) ?></td>
                                <td><input name="amount" type="number" step="0.01" value="<?= h($p["amount"]) ?>"></td>
                                <td><button>Save</button></td>
                            </form>
                        <?php else: ?>
                            <td><?= h($p["placing"]) ?></td>
                            <td><?= money($p["amount"]) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="card" style="margin-top:18px;">
        <h2>Team Owners</h2>
        <table>
            <thead>
                <tr>
                    <th>Team</th>
                    <th>Owner</th>
                    <th>Auto Stage</th>
                    <th>Eliminated</th>
                    <?php if ($canEdit): ?><th>Save Owner</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($teams as $t): ?>
                <tr>
                    <?php if ($canEdit): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="assign_team">
                            <input type="hidden" name="team_id" value="<?= h($t["id"]) ?>">
                            <td>
                                <?php if (!empty($t["flag_url"])): ?>
                                    <img class="flag" src="<?= h($t["flag_url"]) ?>">
                                <?php endif; ?>
                                <?= h($t["team_name"]) ?>
                            </td>
                            <td>
                                <select name="player_id">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($players as $p): ?>
                                        <option value="<?= h($p["id"]) ?>" <?= $p["id"] == $t["player_id"] ? "selected" : "" ?>>
                                            <?= h($p["name"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><?= h($t["stage_reached"]) ?></td>
                            <td><?= $t["eliminated"] ? "Yes" : "No" ?></td>
                            <td><button>Save</button></td>
                        </form>
                    <?php else: ?>
                        <td>
                            <?php if (!empty($t["flag_url"])): ?>
                                <img class="flag" src="<?= h($t["flag_url"]) ?>">
                            <?php endif; ?>
                            <?= h($t["team_name"]) ?>
                        </td>
                        <td><?= h($t["player_name"] ?: "Unassigned") ?></td>
                        <td><?= h($t["stage_reached"]) ?></td>
                        <td><?= $t["eliminated"] ? "Yes" : "No" ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:18px;">
        <h2>Matches / Results</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Stage</th>
                    <th>Match</th>
                    <th>Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matches as $m): ?>
                <?php
                    $statusClass = "status-scheduled";
                    if ($m["status"] === "FINISHED") $statusClass = "status-finished";
                    elseif ($m["status"] === "TIMED") $statusClass = "status-timed";
                ?>
                <tr>
                    <td><?= h($m["match_date"]) ?></td>
                    <td><?= h(substr((string)$m["match_time"], 0, 5)) ?></td>
                    <td><?= h($m["stage"]) ?></td>
                    <td>
                        <?php if (!empty($m["team1_flag"])): ?>
                            <img class="flag" src="<?= h($m["team1_flag"]) ?>">
                        <?php endif; ?>
                        <?= h($m["team1"]) ?>
                        v
                        <?php if (!empty($m["team2_flag"])): ?>
                            <img class="flag" src="<?= h($m["team2_flag"]) ?>">
                        <?php endif; ?>
                        <?= h($m["team2"]) ?>
                    </td>
                    <td>
                        <?php if ($m["team1_score"] !== null && $m["team2_score"] !== null): ?>
                            <?= h($m["team1_score"]) ?> - <?= h($m["team2_score"]) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="<?= h($statusClass) ?>"><?= h($m["status"] ?: "Scheduled") ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>