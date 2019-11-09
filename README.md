# oracle_php
# Lighweight library to work with oracle using php
## Automatically detects the columns of the table

*Of course, you need to have enabled OCI8 php extension.*

See wiki page for documentation. https://github.com/floorin/oracle_php/wiki

After the loadModel() call, results an object that contains the columns of the table as property, and the following methods:
findFirst(), find(), next(), exportAsArray(), create(), update(), delete(), beginTransaction(), rollback(), fetchTable(), checkIfIsNull(), commit(), reset() and of course sql_query(). 
If you find useful and want to use this class but need new features, give me ideas.

https://codreanu.net/lighweight-library-to-work-with-oracle-using-php

A very basic example how to use.

Oracle table:
```sql
CREATE TABLE EMPLOYEE
(EMPNO NUMBER NOT NULL,
FIRSTNAME VARCHAR(30) NOT NULL,
LASTNAME VARCHAR(30) NOT NULL,
BIRTHDATE NOT NULL,
HIREDATE NOT NULL,
JOB VARCHAR(30) NOT NULL,
SALARY NUMBER(7,2)
)
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

if(!$employeeTable->create('commit')){
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

Exporting json:
```php
<?php
require('EmployeeModel.php');
$OCIDB=new OCIdb();
$response = new stdClass();
$response->status="getting salary greather than 1000";
$employeeTable=$OCIDB->loadModel('EmployeeModel');
$employeeTable->find([
		    'conditions' => 'salary>1000',
		    'order by'   =>'salary desc'
		    ]);
$response->rows=$employeeTable->exportAsArray();
die(json_encode($response));		    
?>
```

Just very stupid playing around:
```php
<?php
require('EmployeeModel.php');
$OCIDB=new OCIdb();
$employeeTable=$OCIDB->loadModel('EmployeeModel');
$employeeTable->find([
		    'conditions' => 'lastname like :vlastname',
		    'bind'       => [
					":vlastname" => 'JOHN%'
				    ],
		    'order by'=>'firstname asc'
		    ]);

if($employeeTable->rowExists && $employeeTable->empno==123)
	{ 
	$employeeTable->lastname='Codreanu';
	$employeeTable->update();
	}
$employeeTable->next();
if($employeeTable->rowExists && $employeeTable->lastname='test';)
	{
	$employeeTable->delete();
	}

if($employeeTable->next())//or we can check if next() is still getting data
	{
	$employeeTable->setDataFormat('dd.mm.yyyy');
	$employeeTable->birthdate='25.05.1971';
	$employeeTable->update();
	}
$employeeTable->commit();
?>
```

Adding a bit of complexity declaring the model ("EmployeeModel.php"):
```php
<?php
require('path_to_\OCIdb.php');
class EmployeeModel extends OCIdb{
    public function setSource(){
        $this->table_name='employee';
    }    

      public function initialize()
    {
	//set format data
        $this->setDataFormat('dd.mm.yyyy');
	
	//skip selecting some columns
	$this->skipAttributes(['hiredate','salary']);
	
	//skip some columns on INSERT operation
	$this->skipAttributesOnCreate(['empno']);
	
	//skip some columns on UPDATE operation
	$this->skipAttributesOnUpdate(['empno','firstname']);
    }
    
    /*
    of course, you can redeclare, for instance, an insert/update/delete or whatever parent's method in this model
    and implement your validations.
    */
}
?>
```
