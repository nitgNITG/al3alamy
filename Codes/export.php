<?php
if (isset($_POST['submit'])) {
    require 'vendor/autoload.php'; // Load PhpSpreadsheet

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\IOFactory;
    use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Name');
    $sheet->setCellValue('B1', 'Age');
    $sheet->setCellValue('C1', 'Image');

    $name = $_POST['name'];
    $age = $_POST['age'];
    $images = $_FILES['image'];

    $row = 2; // Start from the second row
    foreach ($name as $key => $value) {
        // Set cell values
        $sheet->setCellValue('A' . $row, $name[$key]);
        $sheet->setCellValue('B' . $row, $age[$key]);

        // Add image
        $imagePath = 'images/' . $images['name'][$key]; // Change the path as needed
        move_uploaded_file($images['tmp_name'][$key], $imagePath);
        $drawing = new Drawing();
        $drawing->setName('Image');
        $drawing->setDescription('Image Description');
        $drawing->setPath($imagePath);
        $drawing->setCoordinates('C' . $row);
        $drawing->setWorksheet($sheet);

        $row++; // Move to the next row
    }

    // Save to a file
    $filename = 'data_with_images.xlsx';
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($filename);

    // Force download the file
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    readfile($filename);

    // Clean up: remove temporary image files
    foreach ($images['tmp_name'] as $tmpName) {
        unlink($tmpName);
    }
    unlink($filename);
}
?>
