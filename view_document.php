<?php
include 'cashloan_db.php';

try {
    // Validate document ID
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("Invalid document ID");
    }
    
    $document_id = (int)$_GET['id'];
    
    // Prepare and execute query using PDO
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = :document_id");
    $stmt->execute([':document_id' => $document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception("Document not found");
    }
    
    // Verify file exists
    if (!file_exists($document['file_path'])) {
        throw new Exception("File not found on server");
    }
    
    // Set appropriate headers based on file type
    $mime_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $file_ext = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
    $content_type = $mime_types[$file_ext] ?? 'application/octet-stream';
    
    // Output the file
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: inline; filename="' . basename($document['document_name']) . '"');
    header('Content-Length: ' . filesize($document['file_path']));
    readfile($document['file_path']);
    
} catch (Exception $e) {
    // Log the error (in a real application, you'd want to log this properly)
    error_log($e->getMessage());
    
    // Display error message
    header('Content-Type: text/html');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Document View Error</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .error { color: #d9534f; background-color: #f2dede; padding: 15px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>Error</h2>
            <p>' . htmlspecialchars($e->getMessage()) . '</p>
            <p><a href="javascript:history.back()">Go back</a></p>
        </div>
    </body>
    </html>';
}
?>