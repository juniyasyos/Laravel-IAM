import { FormEvent, useState } from 'react';
import { Head, router } from '@inertiajs/react';

interface Props {
  email?: string;
  token: string;
}

export default function ResetPassword({ email = '', token }: Props) {
  const [form, setForm] = useState({
    email,
    token,
    password: '',
    password_confirmation: '',
  });
  const [processing, setProcessing] = useState(false);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    setProcessing(true);

    router.post('/reset-password', form, {
      onFinish: () => setProcessing(false),
    });
  };

  return (
    <div className="mx-auto max-w-md p-6">
      <Head title="Reset Password" />
      <h1 className="mb-4 text-2xl font-semibold">Reset Password</h1>
      <form onSubmit={submit} className="space-y-3">
        <input
          type="email"
          value={form.email}
          onChange={(e) => setForm((prev) => ({ ...prev, email: e.target.value }))}
          className="w-full rounded border px-3 py-2"
          placeholder="Email"
          required
        />
        <input
          type="password"
          value={form.password}
          onChange={(e) => setForm((prev) => ({ ...prev, password: e.target.value }))}
          className="w-full rounded border px-3 py-2"
          placeholder="New password"
          required
        />
        <input
          type="password"
          value={form.password_confirmation}
          onChange={(e) => setForm((prev) => ({ ...prev, password_confirmation: e.target.value }))}
          className="w-full rounded border px-3 py-2"
          placeholder="Confirm password"
          required
        />
        <button
          type="submit"
          disabled={processing}
          className="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-60"
        >
          {processing ? 'Resetting...' : 'Reset Password'}
        </button>
      </form>
    </div>
  );
}
