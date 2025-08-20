<?php
require_once 'header.php';

// Handle deletion
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: manage_admins.php");
    exit;
}

// Fetch all admins
$admins = [];
$result = $conn->query("SELECT id, username FROM admins ORDER BY id");
if ($result) {
    $admins = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="admin-content">
    <div class="container">
        <div class="summary-filter">
            <div class="row">
                <div class="field">
                    <h2 style="margin-bottom: 1rem;">Admin Accounts</h2>
                </div>
                <div class="buttons">
                    <a href="add_admin.php" class="btn primary">+ Add Admin</a>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?= htmlspecialchars($admin['id']) ?></td>
                        <td><?= htmlspecialchars($admin['username']) ?></td>
                        <td>
                            <a class="btn primary small" href="edit_admin.php?id=<?= $admin['id'] ?>">Edit</a>
                            <a class="btn danger small" href="?delete=<?= $admin['id'] ?>" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
