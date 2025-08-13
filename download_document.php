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
    
    // Verify file exists and is readable
    if (!file_exists($document['file_path']) || !is_readable($document['file_path'])) {
        throw new Exception("File not available for download");
    }
    
    // Get file information
    $file_path = $document['file_path'];
    $file_name = basename($document['document_name']); // Use original filename
    $file_size = filesize($file_path);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Set MIME types
    $mime_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    // Set appropriate headers
    header('Content-Type: ' . ($mime_types[$file_extension] ?? 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Output the file
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    // Log the error
    error_log('Download Error: ' . $e->getMessage());
    
    // Display error message
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Download Error</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
            .error-container { 
                max-width: 600px; 
                margin: 50px auto; 
                padding: 20px; 
                border: 1px solid #e0e0e0; 
                border-radius: 5px; 
                background-color: #f9f9f9;
            }
            .error-title { 
                color: #d9534f; 
                margin-top: 0;
            }
            .error-message { 
                margin: 15px 0; 
                padding: 10px; 
                background-color: #f2dede; 
                border: 1px solid #ebccd1; 
                border-radius: 4px;
            }
            .back-link { 
                display: inline-block; 
                margin-top: 15px; 
                color: #337ab7; 
                text-decoration: none;
            }
            .back-link:hover { 
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h2 class="error-title">Download Error</h2>
            <div class="error-message">
                <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            <a href="javascript:history.back()" class="back-link">← Go back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>