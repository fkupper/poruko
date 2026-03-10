import './App.css';
import { useHealthQuery } from './api/health.ts';

function App() {
  const { data, isPending, isError, error } = useHealthQuery();

  return (
    <main className="min-h-screen bg-slate-950 text-slate-50 flex items-center justify-center">
      <div className="max-w-md w-full px-6 py-8 rounded-xl border border-slate-800 bg-slate-900 shadow-lg">
        <h1 className="text-2xl font-semibold mb-4">Poruko API health</h1>
        {isPending && <p className="text-slate-300">Checking API health...</p>}
        {isError && (
          <p className="text-red-400">
            Failed to reach API: {(error as Error).message}
          </p>
        )}
        {data && (
          <p className="text-emerald-400">
            API status: <span className="font-mono">{data.status}</span>
          </p>
        )}
      </div>
    </main>
  );
}

export default App;
