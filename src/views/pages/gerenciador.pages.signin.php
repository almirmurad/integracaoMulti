<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="<?=$base;?>/assets/css/style.css" />
    <link rel="stylesheet" type="text/css" href="<?=$base;?>/assets/css/login.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <title>Bicorp - Integrações | Login</title>
</head>
<body>

    <main>
        <aside class="left-area">
            <div class="logo">
                <img src="<?=$base;?>/assets/img/logoBicorp.png" alt="Logotipo">
            </div>
        </aside>
        <section>
                <div class="cadastre">
                    <span>Ainda não se cadastou? <a href="<?=$base?>/cadastro"> Cadastre-se aqui</a></span>
                </div>
                <div class="content-login">
                    <div class="area-form">
                        <form method="POST" action="<?=$base?>/login">
                            <div class="field">
                                <h2>Entrar</h2>
                            </div>
                            
                                <?php if(!empty($flash)):?>
                                    <div class="flash"> <?php echo $flash; ?> </div>
                                <?php endif; ?>
                            
                            <div class="field">
                                <label class="label"><span>Email</span></label>
                            </div>
                            <div class="field">
                                <p><input class="input" name="mail" type="email" placeholder="email@email.com" /></p>
                            </div>
                            <div class="field">
                                <label class="label"><span>Senha</span></label>
                            </div>
                            <div class="field">
                                <p><input class="input" name="pass" type="password" placeholder="Digite sua senha" /></p>
                            </div>
                            <div class="field">
                                <!-- após arrumar o formulário apagar o link e substituir pelo botão -->
                                 <button class="btn-submit" >Enviar</button> 
                                <!-- <a class="btn-submit" href="home">Enviar</a>-->
                            </div>
                        </form>
                    </div>
                </div>
        </section>
    </main>
    
</body>
</html>