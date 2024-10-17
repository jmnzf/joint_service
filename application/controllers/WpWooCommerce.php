<?php
// RECIBE LOS PEDIDOS DESDE WOOCOMMERCE DE WORDPRESS
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'/libraries/REST_Controller.php');
require_once(APPPATH . '/asset/vendor/autoload.php');
use Restserver\libraries\REST_Controller;
use GuzzleHttp\Client;
class WpWooCommerce extends REST_Controller {

	private $pdo;

	public function __construct() {

		header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
		header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding");
		header("Access-Control-Allow-Origin: *");

		parent::__construct();
		$this->load->database();
		$this->pdo = $this->load->database('pdo', true)->conn_id;
        $this->load->library('pedeo', [$this->pdo]);

	}

    // CREAR NUEVA FORMA DE PAGO
	public function createInvoice_post() {

        $Data = $this->post();

        // // Ruta del archivo donde se guardarán los datos
        // $file_path = __DIR__.'/docs/invoice_data.txt';  // Puedes cambiar la ruta según tu estructura de carpetas
        
        // // Convertir los datos a formato JSON (o puedes adaptarlo a otro formato si prefieres)
        // $data_to_save = json_encode($Data, JSON_PRETTY_PRINT);
        
        // // Añadir un separador entre los diferentes JSON para distinguirlos en el archivo
        // $separator = "\n,\n";  // Usamos "---" como separador, puedes cambiarlo por otro
        
        // // Guardar los datos en el archivo, agregando el separador al final de cada JSON
        // file_put_contents($file_path, $data_to_save . $separator, FILE_APPEND | LOCK_EX);


        if ( isset($Data['id']) && $Data['id'] > 0 ) {

            $this->pedeo->trans_begin();

            $sqlInsert = "INSERT INTO tpex(pex_idp, pex_discount_total, pex_discount_tax, pex_shipping_total, pex_shipping_tax, pex_cart_tax,
                        pex_total, pex_total_tax, pex_customer_id, pex_order_key, pex_currency, pex_date_created,
                        pex_first_name, pex_last_name, pex_company, pex_address_1, pex_address_2, pex_city, pex_state, pex_postcode,
                        pex_country, pex_email, pex_phone, business, branch)VALUES(:pex_idp, :pex_discount_total, :pex_discount_tax, :pex_shipping_total,
                        :pex_shipping_tax, :pex_cart_tax, :pex_total, :pex_total_tax, :pex_customer_id, :pex_order_key, :pex_currency,
                        :pex_date_created, :pex_first_name, :pex_last_name, :pex_company, :pex_address_1, :pex_address_2, :pex_city, :pex_state, :pex_postcode,
                        :pex_country, :pex_email, :pex_phone, :business, :branch)";
            
            $resInsert = $this->pedeo->insertRow($sqlInsert, array(
                ':pex_idp' => is_numeric($Data['id']) ? $Data['id'] : 0, 
                ':pex_discount_total' => is_numeric($Data['discount_total']) ? $Data['discount_total']: 0, 
                ':pex_discount_tax' => is_numeric($Data['discount_tax']) ? $Data['discount_tax'] : 0 , 
                ':pex_shipping_total' => is_numeric($Data['shipping_total']) ? $Data['shipping_total'] : 0 , 
                ':pex_shipping_tax' => is_numeric($Data['shipping_tax']) ? $Data['shipping_tax'] : 0 , 
                ':pex_cart_tax' => is_numeric($Data['cart_tax']) ? $Data['cart_tax'] : 0 , 
                ':pex_total' => is_numeric($Data['total']) ? $Data['total'] : 0 , 
                ':pex_total_tax' => is_numeric($Data['total_tax']) ? $Data['total_tax'] : 0 ,  
                ':pex_customer_id' => is_numeric($Data['customer_id']) ? $Data['customer_id'] : 0, 
                ':pex_order_key' => isset($Data['order_key']) ? $Data['order_key'] : '', 
                ':pex_currency' => isset($Data['currency']) ? $Data['currency'] : '', 
                ':pex_date_created' => $Data['date_created'], 
                ':pex_first_name' => isset($Data['billing']['first_name']) ? $Data['billing']['first_name'] : '', 
                ':pex_last_name' => isset($Data['billing']['last_name']) ? $Data['billing']['last_name'] : '', 
                ':pex_company' => isset($Data['billing']['company']) ? $Data['billing']['company'] : '' , 
                ':pex_address_1' => isset($Data['billing']['address_1']) ? $Data['billing']['address_1'] : '' , 
                ':pex_address_2' => isset($Data['billing']['address_2']) ? $Data['billing']['address_2'] : '' , 
                ':pex_city' => isset($Data['billing']['city']) ? $Data['billing']['city'] : '' , 
                ':pex_state' => isset($Data['billing']['state']) ? $Data['billing']['state'] : '' ,  
                ':pex_postcode' => isset($Data['billing']['postcode']) ? $Data['billing']['postcode'] : '' ,
                ':pex_country' => isset($Data['billing']['country']) ? $Data['billing']['country'] : '' , 
                ':pex_email' => isset($Data['billing']['email']) ? $Data['billing']['email'] : '' ,  
                ':pex_phone' => isset($Data['billing']['phone']) ? $Data['billing']['phone'] : '' ,
                ':business' => 1, 
                ':branch' => 1
            ));


            if ( is_numeric($resInsert) && $resInsert > 0 ){

                foreach ($Data['line_items'] as $key => $detail) {

                    $sqlDetail = "INSERT INTO pex1(ex1_name, ex1_product_id, ex1_variation_id, ex1_quantity, ex1_tax_class, ex1_subtotal,
	                            ex1_subtotal_tax, ex1_total, ex1_total_tax, ex1_sku, ex1_price, ex1_idp)VALUES(:ex1_name, :ex1_product_id, :ex1_variation_id,
                                :ex1_quantity, :ex1_tax_class, :ex1_subtotal, :ex1_subtotal_tax, :ex1_total, :ex1_total_tax, :ex1_sku, :ex1_price, :ex1_idp)";
                    
                    $resDetail = $this->pedeo->insertRow($sqlDetail, array(
                        
                        ':ex1_name' => $detail['name'], 
                        ':ex1_product_id' => $detail['product_id'], 
                        ':ex1_variation_id' => $detail['variation_id'],
                        ':ex1_quantity' => $detail['quantity'], 
                        ':ex1_tax_class' => $detail['tax_class'], 
                        ':ex1_subtotal' => $detail['subtotal'], 
                        ':ex1_subtotal_tax' => $detail['subtotal_tax'], 
                        ':ex1_total' => $detail['total'], 
                        ':ex1_total_tax' => $detail['total_tax'], 
                        ':ex1_sku' => $detail['sku'], 
                        ':ex1_price' => $detail['price'],
                        ':ex1_idp' => $Data['id']
                    ));


                    if ( is_numeric($resDetail) && $resDetail > 0 ) {
                    }else{
                        $this->pedeo->trans_rollback();

                        $respuesta = array(
                            'error'   => true,
                            'data' 	  => $resDetail,
                            'mensaje' => 'No se pudo insertar el detalle del producto '.$detail['product_id']
                        );

                        return $this->response($respuesta);
                    }
                   
                }

                $this->pedeo->trans_commit();

            } else {

                $this->pedeo->trans_rollback();

                $respuesta = array(
                    'error'   => true,
                    'data' 	  => $resInsert,
                    'mensaje' => 'No se pudo insertar el pedido '.$Data['id']
                );

                return $this->response($respuesta);
            }

        } else if ( isset( $Data['action'] ) && isset($Data['arg']) ) {

            $sqlInsert = "INSERT INTO cppe(ppe_action, ppe_arg, ppe_status)VALUES(:ppe_action, :ppe_arg, :ppe_status)";

            $resInsert = $this->pedeo->insertRow($sqlInsert, array(
                
                ':ppe_action' => $Data['action'], 
                ':ppe_arg' => $Data['arg'], 
                ':ppe_status' => 0
            ));

            if ( is_numeric($resInsert) && $resInsert > 0 ) {

            }else{
                $respuesta = array(
                    'error'   => true,
                    'data' 	  => $resInsert,
                    'mensaje' => 'No se pudo registrar la informacion del pago para el pedido '.$Data['arg']
                );

                return $this->response($respuesta);
            }

        }

        $respuesta = array(
            'error'   => false,
            'data' 	  => [],
            'mensaje' => 'Proceso finalizado con exito'
        );

        $this->response($respuesta);
	}

    public function getPedido_post(){

        $consumer_key = 'ck_8c30fd22e20976bca2c2c9fb3524be6bc8372733';
        $consumer_secret = 'cs_9855be7515f1d89c30b951c1432d69eb5cc2c47d';
        $store_url = 'https://dev-lr.ventas.com.bo';

        $client = new Client();

        $response = $client->request('GET', $store_url . '/wp-json/wc/v3/orders', [
            'auth' => [$consumer_key, $consumer_secret],
            'query' => [
                'per_page' => 10, // Número de pedidos por página
            ]
        ]);

        $body = $response->getBody();
        $orders = json_decode($body, true);

        print_r(json_encode($orders));

    }

    // CONSULTAR UN PEDIDO










}
