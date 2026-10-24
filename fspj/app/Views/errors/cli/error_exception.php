<?php

use CodeIgniter\CLI\CLI;

// L'exception principale
CLI::write('[' . $exception::class . ']', 'light_gray', 'red');
CLI::write($message);
CLI::write('à ' . CLI::color(clean_path($exception->getFile()) . ':' . $exception->getLine(), 'green'));
CLI::newLine();

$last = $exception;

while ($prevException = $last->getPrevious()) {
    $last = $prevException;

    CLI::write('  Cause :');
    CLI::write('  [' . $prevException::class . ']', 'red');
    CLI::write('  ' . $prevException->getMessage());
    CLI::write('  à ' . CLI::color(clean_path($prevException->getFile()) . ':' . $prevException->getLine(), 'green'));
    CLI::newLine();
}

// La pile d'appels
if (defined('SHOW_DEBUG_BACKTRACE') && SHOW_DEBUG_BACKTRACE) {
    $backtraces = $last->getTrace();

    if ($backtraces) {
        CLI::write('Pile d\'appels :', 'green');
    }

    foreach ($backtraces as $i => $error) {
        $padFile  = '    '; // 4 espaces
        $padClass = '       '; // 7 espaces
        $c        = str_pad($i + 1, 3, ' ', STR_PAD_LEFT);

        if (isset($error['file'])) {
            $filepath = clean_path($error['file']) . ':' . $error['line'];

            CLI::write($c . $padFile . CLI::color($filepath, 'yellow'));
        } else {
            CLI::write($c . $padFile . CLI::color('[fonction interne]', 'yellow'));
        }

        $function = '';

        if (isset($error['class'])) {
            $type = ($error['type'] === '->') ? '()' . $error['type'] : $error['type'];
            $function .= $padClass . $error['class'] . $type . $error['function'];
        } elseif (! isset($error['class']) && isset($error['function'])) {
            $function .= $padClass . $error['function'];
        }

        $args = implode(', ', array_map(static fn ($value): string => match (true) {
            is_object($value) => 'Object(' . $value::class . ')',
            is_array($value)  => $value !== [] ? '[...]' : '[]',
            $value === null   => 'null', // renvoie la version en minuscules
            default           => var_export($value, true),
        }, array_values($error['args'] ?? [])));

        $function .= '(' . $args . ')';

        CLI::write($function);
        CLI::newLine();
    }
}
