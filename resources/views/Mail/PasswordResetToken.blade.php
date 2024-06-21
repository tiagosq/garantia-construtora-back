<div style="text-align: center;">
    <h1>Olá, {{ $fullname }}!</h1>

    <p>Parece que você não se lembra de sua senha, não é?</p>
    <p>Sem problemas! Para criar uma nova basta clicar no botão abaixo:</p>

    <a href="{{ env('APP_URL') }}/reset-password?token={{ $token }}" style="background-color: #494949; color: #fafafa; padding: 10px 20px; border-radius: 10px; text-decoration: none; margin-top: 20px; display: inline-block;">Resetar Senha</a>

    <p style="margin-top: 100px">Porém caso não consiga, você pode copiar o link "{{ env('APP_URL') }}/reset-password?token={{ $token }}" e colar na barra de endereços do seu navegador</p>
</div>
