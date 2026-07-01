<form action="export.php" method="post" enctype="multipart/form-data">
    <input type="text" name="name[]" placeholder="Name">
    <input type="text" name="age[]" placeholder="Age">
    <input type="file" name="image[]">
    <br>
    <input type="text" name="name[]" placeholder="Name">
    <input type="text" name="age[]" placeholder="Age">
    <input type="file" name="image[]">
    <br>
    <!-- Add more rows as needed -->
    <input type="submit" name="submit" value="Export to Excel">
</form>
