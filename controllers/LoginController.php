<?php

namespace Controllers;

use Classes\Email;
use Model\Usuario;
use MVC\Router;

class LoginController{
    public static function login(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $auth = new Usuario($_POST);
            
            $alertas = $auth->validarLogin();

            if(empty($alertas)){
                //Comprobar que el usuario existe
                $usuario = Usuario::where('email', $auth->email);

                
                if($usuario){
                    //Verificar password
                    /** @var Usuario $usuario */
                    if($usuario->comprobarPasswordAndVerificado($auth->password)){
                        session_start();
                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = ($usuario->nombre) . ' ' . ($usuario->apellido ?? null);
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;
                        
                        //Redireccionamiento

                        if($usuario->admin??null === '1'){
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cita');
                        }

                        debuguear($_SESSION);
                    }
                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }
                
            }
           
        }

        $alertas = Usuario::getAlertas();
        $router->render('auth/login',[
            'alertas' => $alertas
        ]);
    }
    
    public static function logout() {
        echo "Desde Logout";
    }

    public static function olvide(Router $router) {
        $router->render('auth/olvide-password');
    }
    
    public static function recuperar() {
        echo "Desde recuperar";
    }

    public static function crear(Router $router) {

        $usuario = new Usuario;


        //Alertas vacias
        $alertas =[];
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $usuario->sincronizar($_POST);    
            $alertas = $usuario->validarNuevaCuenta();    
            
            //Revisar que alerta este vacio
            if(empty($alertas)){
                //Verificar que el usuario no este registrado
                $resultado = $usuario->existeUsuario();

                if($resultado->num_rows){
                    $alertas = Usuario::getAlertas();
                } else{
                    //Hashear el Password
                    $usuario->hashPassword();
    
    
                    //Generar token único
                    $usuario->crearToken();
    
                    //Enviar email
                    $email = new Email( $usuario->email,$usuario->nombre,$usuario->token);
                    $email->enviarConfirmacion();

                    // Crear el usuario
                    $resultado = $usuario->guardar();
                    if($resultado) {
                        header('Location: /mensaje');
                    }

                    //debuguear($usuario);
    
                }
            } 
        }
        $router->render('auth/crear-cuenta',[
            'usuario' => $usuario,
            'alertas' =>$alertas
        ]);       
        
    }

    public static function mensaje(Router $router) {
        $router->render('auth/mensaje');
    }

    public static function confirmar(Router $router) {

        $alertas = [];

        $token = s($_GET['token']);
        /** @var Usuario $usuario */
        $usuario = Usuario::where('token', $token);
        
        if(empty($usuario)){
            //Mostrar mensaje de error
            Usuario::setAlerta('error', 'Token no Válido');

        } else {
            //Modificar a usuario confirmado
           $usuario->confirmado = "1";
           $usuario->token = null;
           $usuario->guardar();
           Usuario::setAlerta('exito', 'Cuenta Comprobada Correctamente');
        }

        //Obtener alertas
        $alertas = Usuario::getAlertas();

        //Renderizar la vista
        $router->render('auth/confirmar-cuenta', [
            'alertas' => $alertas
        ]);
    }
}