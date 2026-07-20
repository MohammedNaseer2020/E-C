<?php
include('config.php'); 

if (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);

    // استعلام عن الحدث
    $stmt = $con->prepare("SELECT * FROM calendar WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        // أعد البيانات على شكل JSON
        echo json_encode([
            'event_id' => $event['event_id'],
            'title' => $event['title'],
            'description' => $event['description'],
            'color' => $event['color'],
            'start_date' => $event['start_date'],
            'end_date' => $event['end_date']
        ]);
    } else {
        echo json_encode(['error' => 'الحدث غير موجود.']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'معرّف الحدث غير محدد.']);
}
?>
