<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class CreateSuperAdmin extends BaseCommand
{
    protected $group       = 'Administration';
    protected $name        = 'admin:create-superadmin';
    protected $description = 'Crée le premier superadministrateur Shield sans stocker de mot de passe dans le dépôt.';
    protected $usage       = 'admin:create-superadmin --email <email> --username <nom-utilisateur> [--password-env PLATFORM_ADMIN_PASSWORD]';
    protected $options     = [
        '--email'        => 'Adresse courriel du compte superadministrateur.',
        '--username'     => 'Nom utilisateur du compte superadministrateur.',
        '--password-env' => 'Nom d’une variable d’environnement contenant le mot de passe. Optionnel.',
    ];

    public function run(array $params): int
    {
        if (! $this->isCentralAdminInstance()) {
            CLI::error('admin:create-superadmin est réservé à l’instance superadministration (app.centralAdminMode=true).');
            CLI::write('Pour créer un administrateur de faculté, utilisez : php spark admin:create-faculty-admin', 'yellow');

            return EXIT_ERROR;
        }

        $email    = $this->optionString('email', $params) ?? CLI::prompt('Adresse email', null, 'required|valid_email|max_length[254]');
        $username = $this->optionString('username', $params) ?? CLI::prompt('Nom utilisateur', null, 'required|min_length[3]|max_length[30]|regex_match[/\A[a-zA-Z0-9\.]+\z/]');
        $password = $this->resolvePassword($params);

        $validation = service('validation');
        $validation->setRules([
            'email'    => 'required|valid_email|max_length[254]',
            'username' => 'required|min_length[3]|max_length[30]|regex_match[/\A[a-zA-Z0-9\.]+\z/]',
            'password' => 'required|min_length[8]',
        ]);

        if (! $validation->run([
            'email'    => $email,
            'username' => $username,
            'password' => $password,
        ])) {
            foreach ($validation->getErrors() as $message) {
                CLI::error($message);
            }

            return EXIT_ERROR;
        }

        if ($this->emailAlreadyExists($email)) {
            CLI::error('Un utilisateur avec cette adresse email existe déjà.');

            return EXIT_ERROR;
        }

        /** @var UserModel $users */
        $users = model(UserModel::class);
        $user  = new User([
            'username' => $username,
            'email'    => $email,
            'password' => $password,
            'active'   => 1,
        ]);

        if (! $users->save($user)) {
            foreach ($users->errors() as $message) {
                CLI::error($message);
            }

            return EXIT_ERROR;
        }

        /** @var User|null $created */
        $created = $users->findById($users->getInsertID());

        if ($created === null) {
            CLI::error('Le compte a été créé, mais il est introuvable pour l’attribution du groupe.');

            return EXIT_ERROR;
        }

        $created->addGroup('superadmin');
        $created->activate();

        CLI::write('Compte superadministrateur créé et activé : ' . $email, 'green');

        return EXIT_SUCCESS;
    }

    private function optionString(string $name): ?string
    {
        $value = CLI::getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function resolvePassword(): string
    {
        $envName = $this->optionString('password-env');

        if ($envName !== null) {
            $password = getenv($envName);

            if (! is_string($password) || $password === '') {
                CLI::error('La variable d’environnement ' . $envName . ' est absente ou vide.');

                return '';
            }

            return $password;
        }

        $password = $this->promptHidden('Mot de passe');
        $confirm  = $this->promptHidden('Confirmation du mot de passe');

        if ($password !== $confirm) {
            CLI::error('Les deux mots de passe ne correspondent pas.');

            return '';
        }

        return $password;
    }

    private function promptHidden(string $label): string
    {
        fwrite(STDOUT, $label . ' : ');

        if (strncasecmp(PHP_OS_FAMILY, 'Windows', 7) !== 0 && function_exists('shell_exec')) {
            shell_exec('stty -echo');
        }

        $value = trim((string) fgets(STDIN));

        if (strncasecmp(PHP_OS_FAMILY, 'Windows', 7) !== 0 && function_exists('shell_exec')) {
            shell_exec('stty echo');
        }

        CLI::newLine();

        return $value;
    }

    private function emailAlreadyExists(string $email): bool
    {
        return db_connect()->table('auth_identities')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->where('secret', $email)
            ->countAllResults() > 0;
    }

    private function isCentralAdminInstance(): bool
    {
        $raw = strtolower(trim((string) env('app.centralAdminMode', 'false')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
}
