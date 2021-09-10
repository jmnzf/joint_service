<?php 

   namespace App\Models;

   use CodeIgniter\Model;

   class MenuModel extends Model
   {
      protected $table      = 'MENU';
      protected $primaryKey = 'Men_Id';

      // protected $returnType = 'array';
      // protected $useSoftDeletes = false;

      protected $allowedFields = [
        'Men_Nombre', 'Men_Icon', 'Men_Controller', 'Men_Action', 'Men_Submenu', 'Men_Idmenu', 'Men_Idestado'
      ];

      protected $useTimestamps = false;
      // protected $createdField  = 'created_at';
      // protected $updatedField  = 'updated_at';
      // protected $deleted_at    = 'deleted_at';
   }
