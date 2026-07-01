<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js"></script>

</head>
<body>
<button id="btnExportCSV">Export CSV with Images</button>
<table id="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Age</th>
            <th>Image</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>John</td>
            <td>25</td>
            <td><img src="https://cdn.pixabay.com/photo/2015/10/05/22/37/blank-profile-picture-973460_1280.png" width="50px" alt="Image 1"></td>
        </tr>
        <tr>
            <td>Jane</td>
            <td>30</td>
            <td><img src="image2.jpg" alt="Image 2"></td>
        </tr>
    </tbody>
</table>


<script>
    $("#btnExportCSV").click(function() {
    const table = $("#data-table");

    const csvData = [];
    const headers = [];
    
    table.find('thead th').each(function() {
        headers.push($(this).text());
    });
    
    csvData.push(headers);
    
    table.find('tbody tr').each(function() {
        const rowData = [];
        $(this).find('td').each(function() {
            if ($(this).is(':last-child')) {
                const imgSrc = $(this).find('img').attr('src');
                rowData.push(imgSrc);
            } else {
                rowData.push($(this).text());
            }
        });
        csvData.push(rowData);
    });

    const csv = Papa.unparse(csvData);
    
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", "data.csv");
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
});

</script>
</body>
</html>