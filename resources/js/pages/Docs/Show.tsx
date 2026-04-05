import { Head } from '@inertiajs/react';
import DocsLayout from '@/layouts/docs-layout';

type NavItem = { slug: string; label: string };

type DocsShowProps = {
    title: string;
    page: string;
    html: string;
    nav: NavItem[];
};

export default function DocsShow({ title, page, html, nav }: DocsShowProps) {
    return (
        <DocsLayout
            nav={nav}
            current={page}
        >
            <Head title={`${title} · Docs`} />
            <article
                className="docs-content max-w-none space-y-4 text-sm leading-relaxed text-neutral-800 dark:text-neutral-200 [&_h1]:mb-2 [&_h1]:scroll-mt-20 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:scroll-mt-20 [&_h2]:border-b [&_h2]:border-neutral-200 [&_h2]:pb-2 [&_h2]:text-xl [&_h2]:font-semibold dark:[&_h2]:border-neutral-700 [&_h3]:mt-6 [&_h3]:text-lg [&_h3]:font-medium [&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-6 [&_li]:my-1 [&_code]:rounded [&_code]:bg-neutral-100 [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-[0.9em] dark:[&_code]:bg-neutral-800 [&_pre]:my-4 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-neutral-900 [&_pre]:p-4 [&_pre]:text-neutral-100 [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_a]:text-emerald-700 [&_a]:underline dark:[&_a]:text-emerald-400 [&_table]:my-4 [&_table]:w-full [&_table]:border-collapse [&_th]:border [&_th]:border-neutral-300 [&_th]:bg-neutral-100 [&_th]:px-2 [&_th]:py-1 [&_th]:text-left dark:[&_th]:border-neutral-600 dark:[&_th]:bg-neutral-800 [&_td]:border [&_td]:border-neutral-300 [&_td]:px-2 [&_td]:py-1 dark:[&_td]:border-neutral-600"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </DocsLayout>
    );
}
