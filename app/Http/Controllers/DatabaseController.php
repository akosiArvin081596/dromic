<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DatabaseController extends Controller
{
    /** @var string[] Tables that cannot be truncated or have rows deleted */
    private const PROTECTED_TABLES = [
        'migrations',
    ];

    /**
     * Get table names for the current database connection only.
     *
     * @return string[]
     */
    private function getTableNames(): array
    {
        $driver = DB::getDriverName();
        $dbName = $driver === 'sqlite'
            ? 'main'
            : config('database.connections.'.config('database.default').'.database');

        return collect(Schema::getTables())
            ->filter(fn (array $table) => $table['schema'] === $dbName)
            ->pluck('name')
            ->all();
    }

    public function index(): Response
    {
        $tableNames = $this->getTableNames();

        $tables = collect($tableNames)
            ->map(fn (string $table) => [
                'name' => $table,
                'rows' => DB::table($table)->count(),
                'columns' => count(Schema::getColumnListing($table)),
                'protected' => in_array($table, self::PROTECTED_TABLES),
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return Inertia::render('Database/Index', [
            'tables' => $tables,
            'driver' => ucfirst(DB::getDriverName() ?? 'unknown'),
            'database' => config('database.connections.'.config('database.default').'.database'),
        ]);
    }

    public function show(Request $request, string $table): Response
    {
        abort_unless(in_array($table, $this->getTableNames()), 404);

        $columns = Schema::getColumnListing($table);
        $perPage = (int) $request->input('per_page', 25);
        $search = $request->input('search', '');
        $sortBy = $request->input('sort', $columns[0] ?? 'rowid');
        $sortDir = $request->input('dir', 'asc');

        abort_unless(in_array($sortDir, ['asc', 'desc']), 422);

        $query = DB::table($table);

        if ($search !== '') {
            $query->where(function ($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if (in_array($sortBy, $columns)) {
            $query->orderBy($sortBy, $sortDir);
        }

        $rows = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Database/Show', [
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'sort' => $sortBy,
                'dir' => $sortDir,
                'per_page' => $perPage,
            ],
            'protected' => in_array($table, self::PROTECTED_TABLES),
        ]);
    }

    public function destroyRow(Request $request, string $table): RedirectResponse
    {
        abort_unless(in_array($table, $this->getTableNames()), 404);
        abort_if(in_array($table, self::PROTECTED_TABLES), 403, 'This table is protected.');

        $request->validate([
            'column' => ['required', 'string'],
            'value' => ['required'],
        ]);

        $column = $request->input('column');
        abort_unless(in_array($column, Schema::getColumnListing($table)), 422);

        DB::table($table)->where($column, $request->input('value'))->limit(1)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Row deleted from {$table}."]);

        return back();
    }

    public function truncate(string $table): RedirectResponse
    {
        abort_unless(in_array($table, $this->getTableNames()), 404);
        abort_if(in_array($table, self::PROTECTED_TABLES), 403, 'This table is protected.');

        DB::table($table)->truncate();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Table {$table} truncated."]);

        return redirect()->route('database.index');
    }
}
