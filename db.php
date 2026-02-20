<?php
session_start();
if(!isset($_SESSION['students'])) {
    $_SESSION['students'] = [];
    $_SESSION['next_id'] = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id = $_SESSION['next_id']++;
        $name = htmlspecialchars(trim($_POST['name'] ?? 'New Student'));
        $age = (int)($_POST['age'] ?? 0);
        $class = htmlspecialchars(trim($_POST['class'] ?? ''));
        $created = date('Y-m-d H:i:s');

        $_SESSION['students'][$id] = [
            'id'=>$id,
            'name'=>$name,
            'age'=>$age,
            'class'=>$class,
            'created'=>$created
        ];
        echo json_encode(['success'=>true,'id'=>$id,'name'=>$name,'age'=>$age,'class'=>$class]);
        exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if(isset($_SESSION['students'][$id])){
            $_SESSION['students'][$id]['name'] = htmlspecialchars(trim($_POST['name'] ?? ''));
            $_SESSION['students'][$id]['age'] = (int)($_POST['age'] ?? 0);
            $_SESSION['students'][$id]['class'] = htmlspecialchars(trim($_POST['class'] ?? ''));
            $_SESSION['students'][$id]['created'] = date('Y-m-d H:i:s'); // update timestamp
            echo json_encode(['success'=>true]);
        } else echo json_encode(['success'=>false]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if(isset($_SESSION['students'][$id])){
            unset($_SESSION['students'][$id]);
            echo json_encode(['success'=>true]);
        } else echo json_encode(['success'=>false]);
        exit;
    }

    if ($action === 'fetch') {
        echo json_encode(['students'=>array_values($_SESSION['students'])]);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Records</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<div class="wrapper">
    <header>
        <h1>Student Records</h1>
        <button class="btn-add" onclick="openAddModal()">+ Add Student</button>
    </header>

    <div class="stats-bar">
        Total Students: <strong id="total-count">0</strong>
        <input type="text" id="search-input" placeholder="Search by name..." oninput="renderTable()" class="search-bar">
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Class</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="student-tbody"></tbody>
        </table>
        <div class="empty-state" id="empty-state">
            No students yet. Click "Add Student" to start.
        </div>
    </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="record-modal">
    <div class="modal">
        <h2 id="modal-title">Add Student</h2>
        <input type="hidden" id="edit-id">
        <input type="text" id="student-name" placeholder="Enter student name">
        <input type="number" id="student-age" placeholder="Enter age">
        <input type="text" id="student-class" placeholder="Enter class">
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="btn-save" onclick="saveRecord()">Save</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
let students = [];

function renderTable(){
    const tbody = document.getElementById('student-tbody');
    const empty = document.getElementById('empty-state');
    const count = document.getElementById('total-count');
    const search = document.getElementById('search-input').value.toLowerCase();
    const filtered = students.filter(s=> s.name.toLowerCase().includes(search));
    count.textContent = filtered.length;

    if(filtered.length===0){ tbody.innerHTML=''; empty.style.display='block'; return; }
    empty.style.display='none';

    tbody.innerHTML = filtered.map(s=>`
        <tr id="row-${s.id}">
            <td>#${String(s.id).padStart(3,'0')}</td>
            <td>${s.name}</td>
            <td>${s.age}</td>
            <td>${s.class}</td>
            <td>${s.created}</td>
            <td>
                <button class="btn-edit" onclick="openEditModal(${s.id})">Edit</button>
                <button class="btn-delete" onclick="deleteRecord(${s.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

function fetchStudents(){
    fetch('',{method:'POST', body:new URLSearchParams({action:'fetch'})})
    .then(res=>res.json())
    .then(data=>{ students=data.students; renderTable(); });
}

function openAddModal(){
    document.getElementById('modal-title').textContent='Add Student';
    ['edit-id','student-name','student-age','student-class'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('record-modal').classList.add('active');
}

function openEditModal(id){
    const s = students.find(st=>st.id==id);
    if(!s) return;
    document.getElementById('modal-title').textContent='Edit Student';
    document.getElementById('edit-id').value=s.id;
    document.getElementById('student-name').value=s.name;
    document.getElementById('student-age').value=s.age;
    document.getElementById('student-class').value=s.class;
    document.getElementById('record-modal').classList.add('active');
}

function closeModal(){ document.getElementById('record-modal').classList.remove('active'); }

function saveRecord(){
    const id=document.getElementById('edit-id').value;
    const data = {
        action: id?'edit':'add',
        name: document.getElementById('student-name').value.trim(),
        age: document.getElementById('student-age').value.trim(),
        class: document.getElementById('student-class').value.trim()
    };
    if(!data.name){ showToast('Enter student name','error'); return; }

    fetch('',{method:'POST', body:new URLSearchParams(id?{...data,id}:data)})
    .then(res=>res.json())
    .then(resp=>{
        if(resp.success){
            if(id){
                const s = students.find(s=>s.id==id);
                Object.assign(s,data);
                showToast('Updated','success');
            }else{
                students.push({...data,id:resp.id});
                showToast('Added','success');
            }
            renderTable(); closeModal();
        } else showToast('Error','error');
    });
}

function deleteRecord(id){
    if(!confirm('Are you sure?')) return;
    fetch('',{method:'POST', body:new URLSearchParams({action:'delete',id})})
    .then(res=>res.json())
    .then(data=>{
        if(data.success){ students=students.filter(s=>s.id!=id); renderTable(); showToast('Deleted','success'); }
        else showToast('Error','error');
    });
}

function showToast(msg,type){
    const t=document.getElementById('toast'); t.textContent=msg; t.className='toast '+type+' show';
    clearTimeout(t._timer); t._timer=setTimeout(()=>t.classList.remove('show'),2500);
}

document.getElementById('record-modal').addEventListener('click', e=>{ if(e.target.id==='record-modal') closeModal(); });
document.getElementById('student-name').addEventListener('keydown',e=>{ if(e.key==='Enter') saveRecord(); });

fetchStudents();
</script>
</body>
</html>