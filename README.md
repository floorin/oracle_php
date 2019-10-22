# oracle_php
# Lighweight library to work with oracle using php
## Automatically detects the columns of the table

*Of course, you need to have enabled OCI8 php extension.*

Returns an object that contains the columns of the table as property, and the following methods:
loadModel(), findFirst(), find(), next(), exportAsArray(), insert(), update(), delete(), beginTransaction(), rollback(), fetchTable(), checkIfIsNull(), commit() and of course sql_query(). 

Very basic example how to use:
Create an oracle table:
```sql
CREATE TABLE EMPLOYEE
(EMPNO NUMBER NOT NULL,
FIRSTNAME VARCHAR(30) NOT NULL,
LASTNAME VARCHAR(30) NOT NULL,
BIRTHDATE NOT NULL)
```

Create php file "EmployeeModel.php":
```php
<?php
require('path_to_\OCIdb.php');
class EmployeeModel extends OCIdb{
    public function setSource(){
        $this->table_name='employee';
    }    
}
?>
```

Create php file "EmployeeController.php":
```php
<?php
require('EmployeeModel.php');
$OCIDB=new OCIdb();
$employeeTable=$OCIDB->loadModel('EmployeeModel');
$employeeTable->empno=123;
$employeeTable->firstname='Florin';
$employeeTable->lastname='Florin';
$employeeTable->setDataFormat('dd.mm.yyyy');
$employeeTable->birthdate='10.05.1971';

if(!$employeeTable->insert('commit')){
	$status="error";
	$messages = $employeeTable->error_message; 
}else{
	$status="success";
}
?>
```

Another basic example:
```php
<?php
require('EmployeeModel.php');
$OCIDB=new OCIdb();
$employeeTable=$OCIDB->loadModel('EmployeeModel');
$employeeTable->findFirst([
		    'conditions' => 'empno = :vempno',
		    'bind'       => [
					":vempno" => 123
				    ]
		    ]);
$employeeTable->lastname='Codreanu';
if(!$employeeTable->update('commit')){
	$status="error";
	$messages = $employeeTable->error_message; 
}else{
	$status="success";
}
?>
```

      
