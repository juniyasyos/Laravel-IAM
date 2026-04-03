import { FormEvent, useState } from 'react';
import { Head, router } from '@inertiajs/react';

export default function ConfirmPassword() {
  const [password, setPassword] = useState('');
  const [processing, setProcessing] = useState(false);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    router.post(
      '/user/confirm-password',
      { password },
      {
        onFinish: () => setProcessing(false),
      }
    );
  };

  return (
    <div className="mx-auto max-w-md p-6">
      <Head title="Confirm Password" />
      <h1 className="mb-4 text-2xl font-semibold">Confirm Password</h1>
      <form onSubmit={submit} className="space-y-3">
        <input
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="w-full rounded border px-3 py-2"
          placeholder="Password"
          required
        />
        <button
          type="submit"
          disabled={processing}
          className="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-60"
        >
          {processing ? 'Confirming...' : 'Confirm'}
        </button>
      </form>
    </div>
  );
}
