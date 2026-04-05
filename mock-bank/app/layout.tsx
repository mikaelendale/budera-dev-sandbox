import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Column mock bank',
  description: 'Sandbox HTTP API for Budera development',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
