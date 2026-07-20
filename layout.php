<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    
    <!-- CSS الأساسية -->
    <link rel="stylesheet" href="css/bootstrap.min.css">           <!-- Bootstrap -->
    <link rel="stylesheet" href="css/bootstrap.rtl.min.css">      <!-- Bootstrap RTL (إذا كنت تستخدم RTL) -->

    <!-- مكتبات UI -->
    <link rel="stylesheet" href="css/all.min.css">    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="css/select2.min.css">            <!-- Select2 -->
    <!-- <link rel="stylesheet" href="css/bootstrap-select.min.css"> -->  <!-- احذفها (تتعارض مع Select2) -->

    <!-- DataTables وملحقاته -->
    <link rel="stylesheet" href="css/jquery.datatables.min.css">  <!-- DataTables الأساسي -->
    <link rel="stylesheet" href="css/dataTables.bootstrap5.min.css"> <!-- تكامل Bootstrap مع DataTables -->
    <link rel="stylesheet" href="css/buttons.datatables.min.css"> <!-- أزرار DataTables -->

    <!-- مكتبات أخرى -->
    <link rel="stylesheet" href="./fullcalendar/lib/main.min.css"> <!-- FullCalendar -->
    <link rel="stylesheet" href="css/ej2/20.2.43/bootstrap5.css">  <!-- Syncfusion (إذا كنت تستخدمه) -->

    <!-- أنماط الموقع الخاصة -->
    <link rel="stylesheet" href="css/site.css">                    <!-- أنماطك الخاصة (يجب أن تكون الأخيرة) -->
</head>

<style>
    :root {
        /* الألوان الأساسية */
        --primary-color:rgb(112, 175, 175);
        --secondary-color:rgb(240, 244, 247);
        --hover-color:rgb(83, 157, 160);
        --light-bg: #f8f9fa;
        --border-color: #e9ecef;
        --text-color: #495057;
        --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        --form-bg: #f9f9f9;
        --success-color: green;
        /* إضافة متغيرات التدرج */
    --gradient-primary-hover: linear-gradient(135deg, var(--hover-color), var(--primary-color));
    --gradient-primary: linear-gradient(135deg, 
                    var(--primary-color) 0%, 
                    var(--hover-color) 50%, 
                    var(--primary-color) 100%);
                  
}

    /* الأنماط العامة */
    body {
        padding: 10px;
        width: 50%;
        margin: 0 10px;
        font-family: 'NotoKufi', sans-serif;
        background-color: #f5f7fa;
        color: var(--text-color);
    }

    /* أنماط البطاقة */
    .card {
        width: 200%;
        margin-left: 10px;
        padding-right: 10px;
        border: none;
        border-radius: 10px;
        box-shadow: var(--shadow);
        transition: all 0.3s ease;
        background: white;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    }

    /* أنماط الجدول */
    #example_wrapper {
        padding: 20px;
        background: white;
        border-radius: 10px;
        box-shadow: var(--shadow);
    }

    #example {
        width: 100% !important;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px;
        overflow: hidden;
    }

    #example thead th {
    background: var(--gradient-primary); /* استخدام التدرج بدلاً من لون خالص */
    color: white;
    font-weight: 600;
    padding: 12px 15px;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 10;
    transition: all 0.3s ease; /* إضافة انتقال سلس */
}

#example thead th:hover {
    background: var(--gradient-primary-hover); /* تغيير التدرج عند التحويم */
    transform: translateY(-2px); /* تأثير رفع بسيط */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* إضافة ظل */
}

    #example tbody td {
        padding: 10px 15px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        font-size: 10px !important;
    }

    #example tbody tr {
        transition: all 0.3s ease;
        color: black;
        background: var(--secondary-color);
    }

    #example tbody tr:hover {
        background-color: var(--light-bg);
        transform: translateX(5px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    #example tbody tr:nth-child(even) {
        background-color: var(--light-bg);
    }

    th {
        vertical-align: middle;
        font-size: 13px !important;
    }

    /* أنماط الأزرار */
    .btn-export {
        background: var(--success-color) !important;
        border: none !important;
        color: #fff !important;
        font-size: 14px !important;
        padding: 2px 5px !important;
    }

    .dt-buttons .btn {
        border-radius: 6px !important;
        margin-right: 5px;
        padding: 8px 15px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
    }

    .dt-buttons .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* أنماط عناصر التحكم */
    .dataTables_filter input {
        border-radius: 20px;
        padding: 8px 15px;
        border: 1px solid var(--border-color);
        width: 300px !important;
    }

    .dataTables_length select {
        border-radius: 6px;
        padding: 5px 10px;
        border: 1px solid var(--border-color);
    }

    .dataTables_paginate .paginate_button {
        border-radius: 6px !important;
        margin: 0 3px !important;
        padding: 5px 12px !important;
        border: 1px solid var(--border-color) !important;
    }

    .dataTables_paginate .paginate_button.current {
        background: var(--primary-color) !important;
        color: white !important;
        border: none !important;
    }

    .dataTables_info {
        padding-top: 15px !important;
        color: #6c757d !important;
    }
    .dataTables_wrapper {
        overflow-x: auto;
    }

    /* أنماط النماذج */
    .slide-form {
        max-width: 530px;
        margin: 0;
        padding: 20px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background-color: var(--form-bg);
        position: fixed;
        top: 5%;
        right: 0;
        z-index: 999;
        display: none;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        transition: transform 0.3s ease-out;
        transform: translateX(100%);
    }

    .slide-form.show {
        transform: translateX(0);
    }

    #eventForm {
        width: 700px;
    }

    /* أنماط إضافية */
    .scrollable-div {
        height: 600px;
        overflow-y: auto;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 5px;
    }

    .input-group {
        display: flex;
        flex-wrap: wrap;
    }

    .form-group {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        width: 100%;
    }

    .form-group label {
        text-align: right;
        width: 40%;
        font-size: 17px;
        font-weight: bold;
    }

    .form-group input,
    .form-group select {
        width: 70%;
    }

    .btn-add {
        width: 100%;
        padding: 10px;
        border: none;
        border-radius: 4px;
        background-color: #007bff;
        color: white;
        font-size: 18px;
        cursor: pointer;
        margin-top: 20px;
        transition: background-color 0.3s;
    }

    .btn-add:hover {
        background-color: #0056b3;
    }

    /* شريط التمرير */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--hover-color);
    }

    /* التجاوب مع الشاشات الصغيرة */
    @media (max-width: 768px) {
        body {
            width: auto;
            margin: 0;
        }

        .card {
            width: 100%;
            margin-left: 0;
        }

        #example_wrapper {
            padding: 10px;
        }

        .dataTables_filter input {
            width: 100% !important;
        }

        .dt-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }

        .dt-buttons .btn {
            flex: 1 1 auto;
            padding: 6px 10px !important;
            font-size: 12px !important;
        }

        .slide-form, #eventForm {
            width: 90%;
            max-width: none;
        }
    }
    #createForm{
            text-align: right;
            max-width: 530px; 
            margin: 0; 
            padding: 20px; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
            background-color: #f9f9f9; 
            position: fixed; /* Fixed position on the screen */
            top: 5%; /* You can adjust this to change the form's vertical position */
            right: 0; /* Align to the right side of the screen */
            z-index: 999; /* Make sure the form is on top of other content */
            display: none; /* Hidden initially */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow for better visibility */
            transition: transform 0.3s ease-out; /* Smooth transition when form appears */
            transform: translateX(100%); /* Initially, the form is off-screen */
        }
        #createForm.show {
            transform: translateX(0); /* Slide the form in */
        }
        #editForm{
            text-align: right;
            max-width: 530px; 
            margin: 0; 
            padding: 20px; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
            background-color: #f9f9f9; 
            position: fixed; /* Fixed position on the screen */
            top: 5%; /* You can adjust this to change the form's vertical position */
            right: 0; /* Align to the right side of the screen */
            z-index: 999; /* Make sure the form is on top of other content */
            display: none; /* Hidden initially */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow for better visibility */
            transition: transform 0.3s ease-out; /* Smooth transition when form appears */
            transform: translateX(100%); /* Initially, the form is off-screen */
        }
        #editForm.show {
            transform: translateX(0); /* Slide the form in */
        }
        #calendarForm{
            max-width: 530px; 
            margin: 0; 
            padding: 20px; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
            background-color: #f9f9f9; 
            position: fixed; /* Fixed position on the screen */
            top: 5%; /* You can adjust this to change the form's vertical position */
            right: 0; /* Align to the right side of the screen */
            z-index: 999; /* Make sure the form is on top of other content */
            display: none; /* Hidden initially */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow for better visibility */
            transition: transform 0.3s ease-out; /* Smooth transition when form appears */
            transform: translateX(100%); /* Initially, the form is off-screen */
        }
        #calendarForm.show {
            transform: translateX(0); /* Slide the form in */
        }
        #eventForm{
            width: 700px; 
            margin: 0; 
            padding: 20px; 
            border: 1px solid #ccc; 
            border-radius: 8px; 
            background-color: #f9f9f9; 
            position: fixed; /* Fixed position on the screen */
            top: 28%; /* You can adjust this to change the form's vertical position */
            right: 0; /* Align to the right side of the screen */
            z-index: 999; /* Make sure the form is on top of other content */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add shadow for better visibility */
            transition: transform 0.3s ease-out; /* Smooth transition when form appears */
        }
        #eventForm.show{
            transform: translateX(1); /* Slide the form in */
        }
        .badge-pending {
            background-color: #ffc107;
            color: #fff;
            padding: 0.5em 0.8em; /* زيادة padding */
            font-size: 1.5em; /* تكبير حجم النص */
            border-radius: 0.25rem;
        }
        .badge-approved {
            background-color: #28a745;
            color: #fff;
            padding: 0.5em 0.8em; /* زيادة padding */
            font-size: 1.5em; /* تكبير حجم النص */
            border-radius: 0.25rem;
        }
        .badge-rejected {
            background-color: #dc3545;
            color: #fff;
            padding: 0.5em 0.8em; /* زيادة padding */
            font-size: 1.5em; /* تكبير حجم النص */
            border-radius: 0.25rem;
        }
         .badge-warning {
            background-color: #ffc107; /* أصفر للتحذير */
            color: #fff;
            padding: 0.5em 0.8em; /* زيادة padding */
            font-size: 1.5em; /* تكبير حجم النص */
            border-radius: 0.25rem;
        }

        .badge-success {
            background-color: #28a745; /* أخضر للنجاح */
            color: #fff;
            padding: 0.5em 0.8em; /* زيادة padding */
            font-size: 1.5em; /* تكبير حجم النص */
            border-radius: 0.50rem;
        }
        .badge-danger{
            background-color: #dc3545;
            color: #fff;
            padding: 0.5em 0.8em; /* زيادة padding */
            font-size: 1.5em; /* تكبير حجم النص */
            border-radius: 0.50rem;
        }
</style>

    <script src="js/jquery-3.6.0.min.js"></script>                  <!-- jQuery (الأول دائمًا) -->
    <script src="js/bootstrap.bundle.min.js"></script>             <!-- Bootstrap (يحتوي على Popper بالفعل، لذا يمكن حذف السطر السابق إذا كان هذا هو الإصدار الكامل) -->

    <!-- مكتبات تعتمد على jQuery -->
    <script src="js/select2.min.js"></script>                      <!-- Select2 -->
    <script src="js/bootstrap-select.min.js"></script>             <!-- Bootstrap Select (اختياري، يتعارض مع Select2) -->

    <!-- DataTables وملحقاته -->
    <script src="js/jquery.datatables.min.js"></script>            <!-- DataTables الأساسي -->
    <script src="js/dataTables.buttons.min.js"></script>           <!-- أزرار DataTables -->
    <script src="js/jszip.min.js"></script>                        <!-- دعم Excel -->
    <script src="js/pdfmake.min.js"></script>                      <!-- دعم PDF -->
    <script src="js/vfs_fonts.js"></script>                        <!-- خطوط PDF -->
    <script src="js/buttons.html5.min.js"></script>                <!-- تصدير إلى HTML5 -->
    <script src="js/buttons.print.min.js"></script>                <!-- طباعة -->
    <script src="js/buttons.colVis.min.js"></script>               <!-- إظهار/إخفاء الأعمدة -->

    <!-- مكتبات أخرى -->
    <script src="./fullcalendar/lib/main.min.js"></script>         <!-- FullCalendar -->
    <script src="js/sweetalert2@11.js"></script>                   <!-- SweetAlert2 -->
<body>

<?php
    include('config.php');
    ?>


    

    <script>
      $(document).ready(function() {
    if (!$.fn.dataTable.isDataTable('#example')) {
        $('#example').DataTable({
            dom: '<"top"lfB>rt<"bottom"ip><"clear">',
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            buttons: [
                {
                    extend: 'copy',
                    text: '<i class="fas fa-files"></i> نسخ',
                    className: 'btn btn-light border',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'csv',
                    text: '<i class="fas fa-filetype-csv"></i> CSV',
                    className: 'btn btn-light border',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-earmark-excel"></i> Excel',
                    className: 'btn btn-light border',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-printer"></i> طباعة',
                    className: 'btn btn-light border',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-eye"></i> أعمدة',
                    className: 'btn btn-light border'
                }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Arabic.json"
            },
            responsive: true,
            initComplete: function() {
                // إضافة تأثير عند تحميل الجدول
                $('#example').css('opacity', '0').animate({'opacity': '1'}, 500);
            },
            drawCallback: function() {
                // إضافة تأثير عند تغيير الصفحة
                $('#example tbody tr').css('opacity', '0').animate({'opacity': '1'}, 300);
            }
        });
        
        // تنسيق أزرار التصدير في مجموعة واحدة
        $('.dt-buttons').addClass('btn-group');
        $('.dt-buttons button').removeClass('btn-secondary').addClass('btn-sm');
    }
});
    </script>
</body>
</html>