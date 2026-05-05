<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPDO();
$id  = (int)($_GET['id'] ?? 0);
$hasAuthorIdColumn = columnExists($pdo, 'blog_posts', 'author_id');
$hasAuthorColumn   = columnExists($pdo, 'blog_posts', 'author');

function ensureBlogUploadDir(array &$errors): ?string
{
    $dir = SITE_ROOT . 'uploads/blog/';

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $errors[] = 'Blog upload folder could not be created: ' . $dir;
        return null;
    }

    if (!is_writable($dir)) {
        $errors[] = 'Blog upload folder is not writable: ' . $dir;
        return null;
    }

    return $dir;
}

$stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) { header('Location: index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $slug         = trim($_POST['slug'] ?? '');
    $excerpt      = trim($_POST['excerpt'] ?? '');
    $content      = trim($_POST['content'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $tags         = trim($_POST['tags'] ?? '');
    $is_published = isset($_POST['is_published']) ? 1 : 0;

    if ($title === '')   $errors[] = 'Title is required.';
    if ($slug === '')    $errors[] = 'Slug is required.';
    if ($content === '') $errors[] = 'Content is required.';

    if ($slug !== '') {
        $chk = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id != ?');
        $chk->execute([$slug, $id]);
        if ($chk->fetch()) $errors[] = 'Slug already exists.';
    }

    // Cover image
    $cover_image = $post['cover_image'];
    if (!empty($_FILES['cover_image']['name'])) {
        $file    = $_FILES['cover_image'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed. Please try again.';
        } elseif (!in_array($file['type'], $allowed, true)) {
            $errors[] = 'Image must be JPG, PNG or WEBP.';
        } elseif ($file['size'] > 25 * 1024 * 1024) {
            $errors[] = 'Image must be under 25MB.';
        } else {
            $uploadDir = ensureBlogUploadDir($errors);
            if ($uploadDir !== null) {
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $name = 'blog_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . $name;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    if ($post['cover_image']) {
                        $old = SITE_ROOT . ltrim($post['cover_image'], '/');
                        if (file_exists($old)) unlink($old);
                    }
                    $cover_image = 'uploads/blog/' . $name;
                } else {
                    $errors[] = 'Failed to upload image. Check folder permissions.';
                }
            }
        }
    }

    if (isset($_POST['remove_image'])) {
        if ($post['cover_image']) {
            $old = SITE_ROOT . ltrim($post['cover_image'], '/');
            if (file_exists($old)) unlink($old);
        }
        $cover_image = null;
    }

    if (empty($errors)) {
        // Set published_at only when first publishing
        $published_at = $post['published_at'];
        if ($is_published && !$post['is_published']) {
            $published_at = date('Y-m-d H:i:s');
        } elseif (!$is_published) {
            $published_at = null;
        }

        $sql = '
            UPDATE blog_posts SET
              title=?, slug=?, excerpt=?, content=?, cover_image=?,
              category=?, tags=?,' . ($hasAuthorIdColumn ? ' author_id=?,' : '') . ($hasAuthorColumn ? ' author=?,' : '') . ' is_published=?, published_at=?
            WHERE id=?
        ';

        $params = [
            $title,
            $slug,
            $excerpt,
            $content,
            $cover_image,
            $category ?: null,
            $tags ?: null,
        ];

        if ($hasAuthorIdColumn) {
            $params[] = (int) $_SESSION['admin_id'];
        }
        if ($hasAuthorColumn) {
            $params[] = trim((string) ($_SESSION['admin_name'] ?? ($post['author'] ?? 'Admin')));
        }

        $params[] = $is_published;
        $params[] = $published_at;
        $params[] = $id;

        $pdo->prepare($sql)->execute($params);
        header('Location: index.php?updated=1'); exit;
    }

    $post = array_merge($post, $_POST);
}

$pageTitle = 'Edit Post';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <h1><i class="bi bi-pencil me-2 text-primary"></i>Edit Post</h1>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0 ps-3">
      <?php foreach ($errors as $e): ?>
        <li><?= htmlspecialchars($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <div class="row g-3">

    <!-- LEFT -->
    <div class="col-lg-8">

      <div class="admin-card mb-3">
        <div class="card-header">Post Details</div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="titleInput" class="form-control"
                   value="<?= htmlspecialchars($post['title']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Slug <span class="text-danger">*</span></label>
            <input type="text" name="slug" id="slugInput" class="form-control"
                   value="<?= htmlspecialchars($post['slug']) ?>" required>
          </div>
          <div>
            <label class="form-label">Excerpt</label>
            <textarea name="excerpt" class="form-control" rows="2"><?= htmlspecialchars($post['excerpt'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Content Editor -->
      <div class="admin-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Content <span class="text-danger">*</span></span>
          <div class="editor-toolbar d-flex gap-1">
            <button type="button" class="toolbar-btn" onclick="fmt('bold')"><i class="bi bi-type-bold"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('italic')"><i class="bi bi-type-italic"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmtHeading('h2')"><i class="bi bi-type-h2"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmtHeading('h3')"><i class="bi bi-type-h3"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('insertUnorderedList')"><i class="bi bi-list-ul"></i></button>
            <button type="button" class="toolbar-btn" onclick="fmt('insertOrderedList')"><i class="bi bi-list-ol"></i></button>
            <button type="button" class="toolbar-btn" onclick="insertLink()"><i class="bi bi-link-45deg"></i></button>
            <div class="toolbar-divider"></div>
            <button type="button" class="toolbar-btn" onclick="toggleSource()" id="sourceBtn"><i class="bi bi-code-slash"></i></button>
          </div>
        </div>
        <div class="p-0">
          <div id="editor" contenteditable="true" class="blog-editor"><?= $post['content'] ?></div>
          <textarea id="sourceEditor" name="content"
                    class="form-control d-none blog-source"
                    rows="18"><?= htmlspecialchars($post['content']) ?></textarea>
        </div>
      </div>

    </div>

    <!-- RIGHT -->
    <div class="col-lg-4">

      <div class="admin-card mb-3">
        <div class="card-header">Publish</div>
        <div class="p-3">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_published"
                   id="isPublished" value="1" <?= $post['is_published'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="isPublished">
              <i class="bi bi-send me-1"></i> Published
            </label>
          </div>
          <?php if ($post['published_at']): ?>
            <div class="text-muted small mb-3">
              <i class="bi bi-clock me-1"></i>
              Published: <?= date('d M Y, h:i A', strtotime($post['published_at'])) ?>
            </div>
          <?php endif; ?>
          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check-lg me-1"></i> Update Post
            </button>
            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Cover Image</div>
        <div class="p-3">
          <?php if ($post['cover_image']): ?>
            <img src="<?= htmlspecialchars(site_url($post['cover_image'])) ?>"
                 id="imagePreview"
                 style="width:100%;height:160px;object-fit:cover;border-radius:8px;margin-bottom:.75rem;">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="remove_image" id="removeImg">
              <label class="form-check-label text-danger small" for="removeImg">Remove image</label>
            </div>
          <?php else: ?>
            <div id="imagePreviewWrap" class="mb-3 d-none">
              <img id="imagePreview" src=""
                   style="width:100%;height:160px;object-fit:cover;border-radius:8px;">
            </div>
          <?php endif; ?>
          <input type="file" name="cover_image" id="imageInput"
                 class="form-control" accept="image/jpeg,image/png,image/webp">
          <div class="form-text">JPG, PNG or WEBP. Max 3MB.</div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <div class="card-header">Meta</div>
        <div class="p-3">
          <div class="mb-3">
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control"
                   value="<?= htmlspecialchars($post['category'] ?? '') ?>"
                   list="catList">
            <datalist id="catList">
              <option value="Travel Tips">
              <option value="Destinations">
              <option value="Culture">
              <option value="Wildlife">
              <option value="Food">
              <option value="Adventure">
            </datalist>
          </div>
          <div>
            <label class="form-label">Tags <small class="text-muted">(comma separated)</small></label>
            <input type="text" name="tags" class="form-control"
                   value="<?= htmlspecialchars($post['tags'] ?? '') ?>">
          </div>
        </div>
      </div>

    </div>
  </div>
</form>

<style>
.blog-editor {
  min-height: 380px; padding: 1.25rem; outline: none;
  font-size: .92rem; line-height: 1.85; color: #2d3748; border: none;
  overflow-wrap: break-word; word-break: break-word; overflow-x: hidden;
}
.blog-editor h2 { font-size: 1.4rem; font-weight: 700; margin: 1rem 0 .5rem; color: #03045E; }
.blog-editor h3 { font-size: 1.15rem; font-weight: 600; margin: .75rem 0 .4rem; }
.blog-editor ul, .blog-editor ol { padding-left: 1.5rem; margin-bottom: .75rem; }
.blog-editor a { color: #0077B6; }
.blog-source { min-height: 380px; font-family: monospace; font-size: .82rem; border: none; border-top: 1px solid #e2e8f0; border-radius: 0; }
.toolbar-btn { width: 30px; height: 30px; border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; color: #4a5568; display: flex; align-items: center; justify-content: center; font-size: .85rem; cursor: pointer; transition: background .15s, color .15s; padding: 0; }
.toolbar-btn:hover { background: #0077B6; color: #fff; border-color: #0077B6; }
.toolbar-divider { width: 1px; background: #e2e8f0; margin: 0 .15rem; align-self: stretch; }
</style>

<script>
document.getElementById('titleInput').addEventListener('input', function () {
  const s = document.getElementById('slugInput');
  if (s.dataset.manual) return;
  s.value = this.value.toLowerCase().trim().replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-');
});
document.getElementById('slugInput').addEventListener('input', function () { this.dataset.manual = 'true'; });

document.getElementById('imageInput').addEventListener('change', function () {
  const wrap = document.getElementById('imagePreviewWrap');
  const prev = document.getElementById('imagePreview');
  if (this.files[0]) {
    const r = new FileReader();
    r.onload = e => { prev.src = e.target.result; if (wrap) wrap.classList.remove('d-none'); };
    r.readAsDataURL(this.files[0]);
  }
});

const editor = document.getElementById('editor');
const sourceEditor = document.getElementById('sourceEditor');
let sourceMode = false;

document.querySelector('form').addEventListener('submit', function () {
  if (!sourceMode) sourceEditor.value = editor.innerHTML;
});

function fmt(cmd) { if (!sourceMode) { editor.focus(); document.execCommand(cmd, false, null); } }
function fmtHeading(tag) { if (!sourceMode) { editor.focus(); document.execCommand('formatBlock', false, tag); } }
function insertLink() {
  if (sourceMode) return;
  const url = prompt('Enter URL:');
  if (url) { editor.focus(); document.execCommand('createLink', false, url); }
}
function toggleSource() {
  sourceMode = !sourceMode;
  const btn = document.getElementById('sourceBtn');
  if (sourceMode) {
    sourceEditor.value = editor.innerHTML;
    editor.classList.add('d-none'); sourceEditor.classList.remove('d-none');
    btn.style.background = '#0077B6'; btn.style.color = '#fff';
  } else {
    editor.innerHTML = sourceEditor.value;
    sourceEditor.classList.add('d-none'); editor.classList.remove('d-none');
    btn.style.background = ''; btn.style.color = '';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
