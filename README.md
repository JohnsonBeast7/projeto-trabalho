# projeto-trabalho

"O projeto a seguir foi criado com intenção única de aprendizado."

Em suma, o projeto é um sistema criado para mostrar uma tabela com informações de usuários sendo elas:

    - Usuário
    - Email
    - Data de admissão
    - Data e hora que as informações foram adicionadas
    - Data e hora que as informações foram alteradas

O sistema foi estruturado com o sistema de arquitetura de software MVC (Model-View-Controller), dividindo a lógica em model, view e controller:

    - O usuário interage com a Visão (interface gráfica).
    - O Controlador recebe a interação do usuário.
    - O Controlador atualiza o Modelo, se necessário.
    - O Controlador solicita à Visão que exiba os dados atualizados.
    - A Visão exibe os dados atualizados ao usuário. 

Na página principal é exibida a tabela com as informações de usuários, ligada a um banco de dados, e três botões com as seguintes funcionalidades:

    -Cadastrar um usuário administrador para adicionar e modificar os usuários da tabela.
    -Login com o usuário administrador cadastrado.
    -Filtro para procurar determinada informação de usuário.

O cadastro e login possuem um campo de Chave de Acesso, para não permitir que um usuário qualquer (Sem a chave de acesso) crie uma conta e acesse o "Dashboard" do sistema.
Existe um usuário denominado "superadmin" o qual não precisa da chave de acesso para logar, somente a senha. (OBS: Esse comportamento não é seguro em um sistema utilizado profissionalmente.)

Ao realizar o login, o administrador é redirecionado para o Dashboard, página onde o mesmo pode adicionar novos usuários na tabela, editar as informações de usuários já existentes, e inativar algum usuário da tabela. A funcionalidade de excluir usuário não foi criada, para que os administradores tenham acesso a todas as informações cadastradas na tabela, sendo possível mudar o status de "ativo" para "inativo", para que a linha seja vista somente pelo administrador no Dashboard.

A atualização da tabela é feita de forma assíncrona, por meio de AJAX (Asynchronous JavaScript and XML). Resumindo, é possível visualizar as adições e alterações da tabela sem que o usuário precise recarregar a página do navegador.

Por fim, as páginas tem um design simples e limpo, agradável visualmente. Os avisos normais do navegador, foram alterados utilizando "SweetAlert2".



-- Todo o desenvolvimento do projeto está corretamente versionado no repositório "projeto-trabalho". --

    


