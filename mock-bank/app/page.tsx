import Link from 'next/link';

export default function Home() {
  return (
    <main style={{ padding: '2rem', maxWidth: '40rem' }}>
      <h1>Column mock bank</h1>
      <p>Sandbox API for local Budera integration.</p>
      <ul>
        <li>
          <Link href="/health">GET /health</Link>
        </li>
        <li>
          <Link href="/control">Control panel</Link>
        </li>
      </ul>
    </main>
  );
}
