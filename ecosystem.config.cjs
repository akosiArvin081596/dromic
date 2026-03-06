module.exports = {
    apps: [
        {
            name: 'dromic-queue',
            interpreter: 'php',
            script: 'artisan',
            args: 'queue:work --sleep=3 --tries=3 --max-time=3600',
            cwd: '/var/www/dromic-is',
            autorestart: true,
            max_restarts: 10,
        },
        {
            name: 'dromic-reverb',
            interpreter: 'php',
            script: 'artisan',
            args: 'reverb:start --host=0.0.0.0 --port=8080',
            cwd: '/var/www/dromic-is',
            autorestart: true,
            max_restarts: 10,
        },
    ],
};
