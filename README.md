# projeto-trabalho

**Este projeto foi desenvolvido com o objetivo exclusivo de aprendizado.**

O sistema consiste em uma aplicação web simples para gerenciamento de usuários, permitindo visualização, adição e modificação de registros por meio de uma interface intuitiva e segura.

    - Nome de usuário
    - Email
    - Data de admissão
    - Data e hora que as informações foram adicionadas
    - Data e hora que as informações foram alteradas

O projeto segue o padrão de arquitetura MVC (Model-View-Controller), que organiza o código em três camadas principais:

    - Model: representa a lógica de dados e acesso ao banco.
    - View: define o layout e apresentação dos dados ao usuário.
    - Controller: faz a ponte entre o usuário e os dados.

Fluxo básico:

    1. O usuário interage com a interface.
    2. O Controller processa a ação.
    3. Se necessário, atualiza o Model.
    4. O Controller envia os dados atualizados de volta à View.
    5. A View exibe os dados atualizados ao usuário. 

Na página principal (/home), é exibida a tabela com as informações de usuários, ligada a um banco de dados, e três botões com as seguintes funcionalidades:

    - Uma tabela de usuários (atualizada em tempo real).
    - Um campo de filtro para busca por nome, e-mail ou status.
    - Botões para:
        - Cadastrar um administrador.
        - Efetuar login como um administrador existente.

O cadastro e login possuem um campo de Chave de Acesso, para não permitir que um usuário qualquer (Sem a chave de acesso) crie uma conta e acesse o "Dashboard" do sistema.
Existe um usuário denominado "superadmin" o qual não precisa da chave de acesso para logar, somente a senha. (Nota: o comportamento de "superadmin" é apenas para fins didáticos e não é recomendado em ambientes de produção.)

Ao realizar o login, o administrador é redirecionado para o Dashboard, página onde o mesmo pode adicionar novos usuários na tabela, editar as informações de usuários já existentes, e inativar algum usuário da tabela. A funcionalidade de excluir usuário não foi criada, visando preservar o histórico completo das informações, garantindo integridade e rastreabilidade.

Algumas tecnologias foram utilizadas na produção, incluindo:

    - PHP - Construção da estrutura do sistema
    - JavaScrip (AJAX) - Atualização da tabela de forma assíncrona, sem recarregar a página
    - MySQL - Manipulação do banco de dados
    - SweetAlert2 - Estilização de avisos e erros

Por fim, o sistema oferece uma interface limpa, com atualizações dinâmicas que tornam a navegação fluida. Ao realizar operações (como cadastro, login ou alteração de dados), o usuário é notificado com janelas modais estilizadas, tornando a experiência mais profissional.



 **Todo o desenvolvimento do projeto está corretamente versionado no repositório "projeto-trabalho".** 

    


