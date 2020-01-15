```php
<?php
/*
create this table before tu run the script
CREATE TABLE EMPLOYEE
(EMPNO NUMBER NOT NULL,
FIRSTNAME VARCHAR(30) NOT NULL,
LASTNAME VARCHAR(30) NOT NULL,
BIRTHDATE DATE NOT NULL,
HIREDATE DATE NOT NULL,
JOB VARCHAR(30) NOT NULL,
SALARY NUMBER(7,2)
)
*/
require_once('OCIdb.php');
class EmployeeModel extends OCIdb{
        public function setSource(){
                $this->_table_name='EMPLOYEE';
        }


       public function initialize()
        {
                // Skips fields/columns on all operations: SLECT/INSERT/UPDATE operations
                $this->setDataFormat('dd.mm.yyyy');
        }
}


$user='xxxx';
$password='yyyyyy';
$ident='ip_or_computer_name:1523/THE_SID';
$dbOCIPHP=new OCIdb();
if(!$dbOCIPHP->sql_connect($user,$password,$ident)){
        die('No database connection! Reason: '.$dbOCIPHP->_error_message);
        }



$EmployeeController=$dbOCIPHP->loadModel('EmployeeModel');


//insert a record
$EmployeeController->empno=123;
$EmployeeController->firstname ='John';
$EmployeeController->lastname ='Doe';
$EmployeeController->birthdate ='07.06.1978';
$EmployeeController->hiredate ='01.11.2019';
$EmployeeController->job  ='programmer';
$EmployeeController->salary  =2200;
if(!$EmployeeController->create('commit')){
    echo "Error! Reason:".$EmployeeController->_error_message;
}

//insert a new record
$EmployeeController->reset();
$EmployeeController->empno=124;
$EmployeeController->firstname ='Mary';
$EmployeeController->lastname ='Smith';
$EmployeeController->birthdate ='26.10.1992';
$EmployeeController->hiredate ='01.11.2019';
$EmployeeController->job  ='programmer';
$EmployeeController->salary  =2200;
if(!$EmployeeController->create('commit')){
    echo "Error! Reason:".$EmployeeController->_error_message;
}


//insert a new record
$EmployeeController->reset();
$EmployeeController->empno=121;
$EmployeeController->firstname ='Helen';
$EmployeeController->lastname ='Richards';
$EmployeeController->birthdate ='31.01.1972';
$EmployeeController->hiredate ='27.01.2018';
$EmployeeController->job  ='chief';
$EmployeeController->salary  =3500;
if(!$EmployeeController->create('commit')){
    echo "Error! Reason:".$EmployeeController->_error_message;
}

//find one record and update them
if($EmployeeController->findFirst([
            'conditions' => 'empno=:empno',
            'bind'       => [
                    ":empno" => 124
                    ]
            ])){//exista deja
                $EmployeeController->job  ='senior programmer';
                $EmployeeController->salary  =2500;

                if(!$EmployeeController->update('commit')){
                        echo "Error! Reason:".$EmployeeController->_error_message;
                    }
            }

//find all records upon conditions
$EmployeeController->find([
            'conditions' => 'job like :job',
            'bind'       => [
                    ":job" => '%programmer%'
                    ]
            ]);

while($EmployeeController->rowExists){
    $EmployeeController->salary  +=200;
    if(!$EmployeeController->update('commit')){
            echo "Error! Reason:".$EmployeeController->_error_message;
        }
    $EmployeeController->next();
}


//use transactions
$EmployeeController->beginTransaction('SAVEPOINT1');
$thereAreErrors=false;
while($EmployeeController->rowExists){
    $EmployeeController->salary  +=200;
    if(!$EmployeeController->update('nocommit')){$thereAreErrors=true;}
    $EmployeeController->next();
}
if(!$thereAreErrors){
$EmployeeController->rollback('SAVEPOINT1');
}else{
$EmployeeController->commit();
}
?>
```
