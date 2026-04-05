import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from '@phosphor-icons/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import CompanySettingsLayout from '@/layouts/company/layout';
import company from '@/routes/company';
import type { BreadcrumbItem } from '@/types';

type Questionnaire = {
    entity: {
        legal_name: string;
        entity_type: 'individual' | 'sole_proprietor' | 'company';
        country: string;
        date_of_birth: string;
        registration_number: string;
        registered_address: string;
    };
    ownership: {
        primary_operator_name: string;
        primary_operator_role: string;
        beneficial_owners: string;
        control_person_ai: string;
    };
    contact: {
        email: string;
        phone: string;
        website_url: string;
        product_description: string;
    };
    platform: {
        usage_internal: boolean;
        usage_platform: boolean;
    };
    end_user_exposure: {
        launch_estimate: string;
        end_users_have_wallets: string;
        agents_act_for_users: string;
        funds_owner: string;
        user_agent_interaction: string;
    };
    compliance: {
        kyc_on_end_users: string;
        kyc_provider: string;
        kyc_data_collected: string;
        kyc_no_explanation: string;
        sanctions_screening: string;
    };
    agent: {
        actions: string[];
        autonomy_level: string;
    };
    financial: {
        max_transaction_amount: string;
        expected_monthly_volume: string;
        expected_tx_per_month: string;
        supported_regions: string;
    };
    funds_flow: {
        source: string;
        destination: string;
        hold_funds_others: string;
        description: string;
    };
    controls: {
        spending_limits_per_agent: string;
        users_override_cancel: string;
        log_agent_actions: string;
        realtime_monitoring: string;
        kill_switch: string;
    };
    risk: {
        worst_case_failure: string;
        incorrect_payments: string;
        compromised_accounts: string;
        prompt_injection: string;
    };
    integration: {
        backend: boolean;
        client_side: boolean;
        api_use_case: string;
        webhook_endpoint: string;
        hosting_region: string;
    };
    declarations: {
        no_anonymous_financial: boolean;
        aml_sanctions: boolean;
        end_user_activity_responsibility: boolean;
        terms_of_service: boolean;
    };
};

function emptyQuestionnaire(prefillEmail: string): Questionnaire {
    return {
        entity: {
            legal_name: '',
            entity_type: 'company',
            country: '',
            date_of_birth: '',
            registration_number: '',
            registered_address: '',
        },
        ownership: {
            primary_operator_name: '',
            primary_operator_role: '',
            beneficial_owners: '',
            control_person_ai: '',
        },
        contact: {
            email: prefillEmail,
            phone: '',
            website_url: 'https://',
            product_description: '',
        },
        platform: {
            usage_internal: false,
            usage_platform: false,
        },
        end_user_exposure: {
            launch_estimate: '',
            end_users_have_wallets: '',
            agents_act_for_users: '',
            funds_owner: '',
            user_agent_interaction: '',
        },
        compliance: {
            kyc_on_end_users: '',
            kyc_provider: '',
            kyc_data_collected: '',
            kyc_no_explanation: '',
            sanctions_screening: '',
        },
        agent: {
            actions: [],
            autonomy_level: '',
        },
        financial: {
            max_transaction_amount: '',
            expected_monthly_volume: '',
            expected_tx_per_month: '',
            supported_regions: '',
        },
        funds_flow: {
            source: '',
            destination: '',
            hold_funds_others: '',
            description: '',
        },
        controls: {
            spending_limits_per_agent: '',
            users_override_cancel: '',
            log_agent_actions: '',
            realtime_monitoring: '',
            kill_switch: '',
        },
        risk: {
            worst_case_failure: '',
            incorrect_payments: '',
            compromised_accounts: '',
            prompt_injection: '',
        },
        integration: {
            backend: false,
            client_side: false,
            api_use_case: '',
            webhook_endpoint: '',
            hosting_region: '',
        },
        declarations: {
            no_anonymous_financial: false,
            aml_sanctions: false,
            end_user_activity_responsibility: false,
            terms_of_service: false,
        },
    };
}

const AGENT_ACTIONS = [
    { id: 'view_balances', label: 'View balances' },
    { id: 'initiate_payments', label: 'Initiate payments' },
    { id: 'receive_funds', label: 'Receive funds' },
    { id: 'manage_budgets', label: 'Manage budgets' },
] as const;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'KYB application', href: company.kyb.form.url() },
];

export default function CompanyKybSubmit() {
    const page = usePage<{ prefillEmail: string }>();
    const { prefillEmail } = page.props;

    const form = useForm({
        questionnaire: emptyQuestionnaire(prefillEmail ?? ''),
        document_government_id: null as File | null,
        document_certificate_incorporation: null as File | null,
        document_director_id: null as File | null,
    });

    const q = form.data.questionnaire;
    const setQ = (patch: Partial<Questionnaire>) => {
        form.setData('questionnaire', { ...form.data.questionnaire, ...patch });
    };

    const setEntity = (patch: Partial<Questionnaire['entity']>) => {
        setQ({ entity: { ...form.data.questionnaire.entity, ...patch } });
    };
    const setOwnership = (patch: Partial<Questionnaire['ownership']>) => {
        setQ({ ownership: { ...form.data.questionnaire.ownership, ...patch } });
    };
    const setContact = (patch: Partial<Questionnaire['contact']>) => {
        setQ({ contact: { ...form.data.questionnaire.contact, ...patch } });
    };
    const setPlatform = (patch: Partial<Questionnaire['platform']>) => {
        setQ({ platform: { ...form.data.questionnaire.platform, ...patch } });
    };
    const setEndUser = (patch: Partial<Questionnaire['end_user_exposure']>) => {
        setQ({
            end_user_exposure: { ...form.data.questionnaire.end_user_exposure, ...patch },
        });
    };
    const setCompliance = (patch: Partial<Questionnaire['compliance']>) => {
        setQ({ compliance: { ...form.data.questionnaire.compliance, ...patch } });
    };
    const setAgent = (patch: Partial<Questionnaire['agent']>) => {
        setQ({ agent: { ...form.data.questionnaire.agent, ...patch } });
    };
    const setFinancial = (patch: Partial<Questionnaire['financial']>) => {
        setQ({ financial: { ...form.data.questionnaire.financial, ...patch } });
    };
    const setFundsFlow = (patch: Partial<Questionnaire['funds_flow']>) => {
        setQ({ funds_flow: { ...form.data.questionnaire.funds_flow, ...patch } });
    };
    const setControls = (patch: Partial<Questionnaire['controls']>) => {
        setQ({ controls: { ...form.data.questionnaire.controls, ...patch } });
    };
    const setRisk = (patch: Partial<Questionnaire['risk']>) => {
        setQ({ risk: { ...form.data.questionnaire.risk, ...patch } });
    };
    const setIntegration = (patch: Partial<Questionnaire['integration']>) => {
        setQ({ integration: { ...form.data.questionnaire.integration, ...patch } });
    };
    const setDeclarations = (patch: Partial<Questionnaire['declarations']>) => {
        setQ({
            declarations: { ...form.data.questionnaire.declarations, ...patch },
        });
    };

    const toggleAgentAction = (id: string, checked: boolean) => {
        const next = new Set(q.agent.actions);
        if (checked) {
            next.add(id);
        } else {
            next.delete(id);
        }
        setAgent({ actions: [...next] });
    };

    const err = (key: string) => {
        const errors = form.errors as Record<string, string>;
        return errors[key];
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="KYB application" />

            <CompanySettingsLayout>
                <div className="mx-auto max-w-4xl space-y-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <Heading
                            variant="small"
                            title="KYB application"
                            description="Complete all sections. Budera reviews this before enabling live API access."
                        />
                        <Button variant="outline" size="sm" asChild>
                            <Link href={company.settings.url()} className="gap-2">
                                <ArrowLeft className="size-4" />
                                Back to settings
                            </Link>
                        </Button>
                    </div>

                    <form
                        className="space-y-6"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(company.kyb.submit.url(), {
                                forceFormData: true,
                                preserveScroll: true,
                            });
                        }}
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Section 1 — Entity information
                                </CardTitle>
                                <CardDescription>Legal entity details.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="legal_name">Legal name</Label>
                                    <Input
                                        id="legal_name"
                                        value={q.entity.legal_name}
                                        onChange={(e) => setEntity({ legal_name: e.target.value })}
                                        required
                                    />
                                    <InputError message={err('questionnaire.entity.legal_name')} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Entity type</Label>
                                    <Select
                                        value={q.entity.entity_type}
                                        onValueChange={(v) =>
                                            setEntity({
                                                entity_type: v as Questionnaire['entity']['entity_type'],
                                            })
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="individual">Individual</SelectItem>
                                            <SelectItem value="sole_proprietor">Sole proprietor</SelectItem>
                                            <SelectItem value="company">Company</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={err('questionnaire.entity.entity_type')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="country">Country of residence / incorporation</Label>
                                    <Input
                                        id="country"
                                        value={q.entity.country}
                                        onChange={(e) => setEntity({ country: e.target.value })}
                                        required
                                    />
                                    <InputError message={err('questionnaire.entity.country')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="dob">Date of birth (if individual)</Label>
                                    <Input
                                        id="dob"
                                        type="date"
                                        value={q.entity.date_of_birth}
                                        onChange={(e) => setEntity({ date_of_birth: e.target.value })}
                                    />
                                    <InputError message={err('questionnaire.entity.date_of_birth')} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="reg">Company registration number (if applicable)</Label>
                                    <Input
                                        id="reg"
                                        value={q.entity.registration_number}
                                        onChange={(e) =>
                                            setEntity({ registration_number: e.target.value })
                                        }
                                    />
                                    <InputError message={err('questionnaire.entity.registration_number')} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="addr">Registered address</Label>
                                    <Textarea
                                        id="addr"
                                        value={q.entity.registered_address}
                                        onChange={(e) =>
                                            setEntity({ registered_address: e.target.value })
                                        }
                                        rows={3}
                                        required
                                    />
                                    <InputError message={err('questionnaire.entity.registered_address')} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Section 2 — Ownership &amp; control
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="po_name">Primary operator / account owner</Label>
                                    <Input
                                        id="po_name"
                                        value={q.ownership.primary_operator_name}
                                        onChange={(e) =>
                                            setOwnership({ primary_operator_name: e.target.value })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.ownership.primary_operator_name')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="po_role">Role (founder, developer, admin, etc.)</Label>
                                    <Input
                                        id="po_role"
                                        value={q.ownership.primary_operator_role}
                                        onChange={(e) =>
                                            setOwnership({ primary_operator_role: e.target.value })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.ownership.primary_operator_role')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="beneficial">
                                        Beneficial owners (&gt;25%) — if company
                                    </Label>
                                    <Textarea
                                        id="beneficial"
                                        value={q.ownership.beneficial_owners}
                                        onChange={(e) =>
                                            setOwnership({ beneficial_owners: e.target.value })
                                        }
                                        rows={2}
                                    />
                                    <InputError
                                        message={err('questionnaire.ownership.beneficial_owners')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="control_ai">Control person responsible for AI system</Label>
                                    <Textarea
                                        id="control_ai"
                                        value={q.ownership.control_person_ai}
                                        onChange={(e) =>
                                            setOwnership({ control_person_ai: e.target.value })
                                        }
                                        rows={2}
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.ownership.control_person_ai')}
                                    />
                                </div>
                                <div className="grid gap-4 rounded-lg border border-border p-4">
                                    <p className="text-sm font-medium">Required uploads</p>
                                    {(q.entity.entity_type === 'individual' ||
                                        q.entity.entity_type === 'sole_proprietor') && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="gov_id">Government ID</Label>
                                            <Input
                                                id="gov_id"
                                                type="file"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                onChange={(e) =>
                                                    form.setData(
                                                        'document_government_id',
                                                        e.target.files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                            <InputError message={err('document_government_id')} />
                                        </div>
                                    )}
                                    {q.entity.entity_type === 'company' && (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="cert">Certificate of incorporation</Label>
                                                <Input
                                                    id="cert"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png"
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'document_certificate_incorporation',
                                                            e.target.files?.[0] ?? null,
                                                        )
                                                    }
                                                />
                                                <InputError
                                                    message={err('document_certificate_incorporation')}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="dir_id">Director ID</Label>
                                                <Input
                                                    id="dir_id"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png"
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'document_director_id',
                                                            e.target.files?.[0] ?? null,
                                                        )
                                                    }
                                                />
                                                <InputError message={err('document_director_id')} />
                                            </div>
                                        </>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 3 — Contact &amp; presence</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email (OTP may be required)</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={q.contact.email}
                                        onChange={(e) => setContact({ email: e.target.value })}
                                        required
                                    />
                                    <InputError message={err('questionnaire.contact.email')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone number</Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={q.contact.phone}
                                        onChange={(e) => setContact({ phone: e.target.value })}
                                        required
                                    />
                                    <InputError message={err('questionnaire.contact.phone')} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="web">Website / product URL</Label>
                                    <Input
                                        id="web"
                                        type="url"
                                        value={q.contact.website_url}
                                        onChange={(e) => setContact({ website_url: e.target.value })}
                                        required
                                    />
                                    <InputError message={err('questionnaire.contact.website_url')} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="desc">Description of product</Label>
                                    <Textarea
                                        id="desc"
                                        value={q.contact.product_description}
                                        onChange={(e) =>
                                            setContact({ product_description: e.target.value })
                                        }
                                        rows={4}
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.contact.product_description')}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 4 — Platform usage</CardTitle>
                                <CardDescription>Select at least one.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={q.platform.usage_internal}
                                        onCheckedChange={(c) =>
                                            setPlatform({ usage_internal: c === true })
                                        }
                                    />
                                    Internal use only (your own agents)
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={q.platform.usage_platform}
                                        onCheckedChange={(c) =>
                                            setPlatform({ usage_platform: c === true })
                                        }
                                    />
                                    Provide agent capabilities to end users (platform model)
                                </label>
                                <InputError message={err('questionnaire.platform.usage_internal')} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Section 5 — End-user exposure
                                </CardTitle>
                                <CardDescription>
                                    Required if you provide capabilities to end users.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Estimated end users at launch</Label>
                                    <Input
                                        value={q.end_user_exposure.launch_estimate}
                                        onChange={(e) =>
                                            setEndUser({ launch_estimate: e.target.value })
                                        }
                                    />
                                    <InputError
                                        message={err('questionnaire.end_user_exposure.launch_estimate')}
                                    />
                                </div>
                                <YesNoRow
                                    label="Will end users have wallets?"
                                    value={q.end_user_exposure.end_users_have_wallets}
                                    onChange={(v) => setEndUser({ end_users_have_wallets: v })}
                                    error={err('questionnaire.end_user_exposure.end_users_have_wallets')}
                                />
                                <YesNoRow
                                    label="Will agents act on behalf of end users?"
                                    value={q.end_user_exposure.agents_act_for_users}
                                    onChange={(v) => setEndUser({ agents_act_for_users: v })}
                                    error={err('questionnaire.end_user_exposure.agents_act_for_users')}
                                />
                                <div className="grid gap-2">
                                    <Label>Who legally owns the funds?</Label>
                                    <Select
                                        value={q.end_user_exposure.funds_owner || '__empty__'}
                                        onValueChange={(v) =>
                                            setEndUser({
                                                funds_owner: v === '__empty__' ? '' : v,
                                            })
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__empty__">—</SelectItem>
                                            <SelectItem value="end_users">End users</SelectItem>
                                            <SelectItem value="business">Business</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={err('questionnaire.end_user_exposure.funds_owner')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>How users interact with agents</Label>
                                    <Textarea
                                        value={q.end_user_exposure.user_agent_interaction}
                                        onChange={(e) =>
                                            setEndUser({ user_agent_interaction: e.target.value })
                                        }
                                        rows={3}
                                    />
                                    <InputError
                                        message={err(
                                            'questionnaire.end_user_exposure.user_agent_interaction',
                                        )}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Section 6 — End-user verification &amp; compliance
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <YesNoRow
                                    label="Do you perform KYC on end users?"
                                    value={q.compliance.kyc_on_end_users}
                                    onChange={(v) => setCompliance({ kyc_on_end_users: v })}
                                    error={err('questionnaire.compliance.kyc_on_end_users')}
                                />
                                <div className="grid gap-2">
                                    <Label>If yes — KYC method / provider</Label>
                                    <Input
                                        value={q.compliance.kyc_provider}
                                        onChange={(e) =>
                                            setCompliance({ kyc_provider: e.target.value })
                                        }
                                    />
                                    <InputError message={err('questionnaire.compliance.kyc_provider')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>If yes — data collected (ID, phone, email, etc.)</Label>
                                    <Textarea
                                        value={q.compliance.kyc_data_collected}
                                        onChange={(e) =>
                                            setCompliance({ kyc_data_collected: e.target.value })
                                        }
                                        rows={2}
                                    />
                                    <InputError
                                        message={err('questionnaire.compliance.kyc_data_collected')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>If no — explain why</Label>
                                    <Textarea
                                        value={q.compliance.kyc_no_explanation}
                                        onChange={(e) =>
                                            setCompliance({ kyc_no_explanation: e.target.value })
                                        }
                                        rows={2}
                                    />
                                    <InputError
                                        message={err('questionnaire.compliance.kyc_no_explanation')}
                                    />
                                </div>
                                <YesNoRow
                                    label="Do you screen users against sanctions lists?"
                                    value={q.compliance.sanctions_screening}
                                    onChange={(v) => setCompliance({ sanctions_screening: v })}
                                    error={err('questionnaire.compliance.sanctions_screening')}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 7 — Agent functionality</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm text-muted-foreground">What actions can agents perform?</p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {AGENT_ACTIONS.map((a) => (
                                        <label
                                            key={a.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={q.agent.actions.includes(a.id)}
                                                onCheckedChange={(c) =>
                                                    toggleAgentAction(a.id, c === true)
                                                }
                                            />
                                            {a.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={err('questionnaire.agent.actions')} />
                                <div className="grid gap-2">
                                    <Label>Level of autonomy</Label>
                                    <Select
                                        value={q.agent.autonomy_level || '__empty__'}
                                        onValueChange={(v) =>
                                            setAgent({
                                                autonomy_level: v === '__empty__' ? '' : v,
                                            })
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__empty__">—</SelectItem>
                                            <SelectItem value="manual">Manual approval required</SelectItem>
                                            <SelectItem value="partial">Partial automation</SelectItem>
                                            <SelectItem value="full_within_limits">
                                                Fully autonomous within limits
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={err('questionnaire.agent.autonomy_level')} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 8 — Financial limits &amp; usage</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Maximum transaction amount (EUR / USD)</Label>
                                    <Input
                                        value={q.financial.max_transaction_amount}
                                        onChange={(e) =>
                                            setFinancial({ max_transaction_amount: e.target.value })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.financial.max_transaction_amount')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Expected monthly volume</Label>
                                    <Input
                                        value={q.financial.expected_monthly_volume}
                                        onChange={(e) =>
                                            setFinancial({ expected_monthly_volume: e.target.value })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.financial.expected_monthly_volume')}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Expected transactions per month</Label>
                                    <Input
                                        value={q.financial.expected_tx_per_month}
                                        onChange={(e) =>
                                            setFinancial({ expected_tx_per_month: e.target.value })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.financial.expected_tx_per_month')}
                                    />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label>Supported regions (countries)</Label>
                                    <Textarea
                                        value={q.financial.supported_regions}
                                        onChange={(e) =>
                                            setFinancial({ supported_regions: e.target.value })
                                        }
                                        rows={2}
                                        required
                                    />
                                    <InputError
                                        message={err('questionnaire.financial.supported_regions')}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 9 — Funds flow</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Source of funds</Label>
                                    <Textarea
                                        value={q.funds_flow.source}
                                        onChange={(e) => setFundsFlow({ source: e.target.value })}
                                        rows={2}
                                        required
                                    />
                                    <InputError message={err('questionnaire.funds_flow.source')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Destination of funds</Label>
                                    <Textarea
                                        value={q.funds_flow.destination}
                                        onChange={(e) =>
                                            setFundsFlow({ destination: e.target.value })
                                        }
                                        rows={2}
                                        required
                                    />
                                    <InputError message={err('questionnaire.funds_flow.destination')} />
                                </div>
                                <YesNoRow
                                    label="Do you hold funds on behalf of others?"
                                    value={q.funds_flow.hold_funds_others}
                                    onChange={(v) => setFundsFlow({ hold_funds_others: v })}
                                    error={err('questionnaire.funds_flow.hold_funds_others')}
                                />
                                <div className="grid gap-2">
                                    <Label>Describe flow (text)</Label>
                                    <Textarea
                                        value={q.funds_flow.description}
                                        onChange={(e) =>
                                            setFundsFlow({ description: e.target.value })
                                        }
                                        rows={4}
                                        required
                                    />
                                    <InputError message={err('questionnaire.funds_flow.description')} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 10 — Controls &amp; safeguards</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                <YesNoRow
                                    label="Spending limits per agent?"
                                    value={q.controls.spending_limits_per_agent}
                                    onChange={(v) =>
                                        setControls({ spending_limits_per_agent: v })
                                    }
                                    error={err('questionnaire.controls.spending_limits_per_agent')}
                                />
                                <YesNoRow
                                    label="Can users override or cancel transactions?"
                                    value={q.controls.users_override_cancel}
                                    onChange={(v) =>
                                        setControls({ users_override_cancel: v })
                                    }
                                    error={err('questionnaire.controls.users_override_cancel')}
                                />
                                <YesNoRow
                                    label="Do you log all agent actions?"
                                    value={q.controls.log_agent_actions}
                                    onChange={(v) => setControls({ log_agent_actions: v })}
                                    error={err('questionnaire.controls.log_agent_actions')}
                                />
                                <YesNoRow
                                    label="Real-time monitoring / alerts?"
                                    value={q.controls.realtime_monitoring}
                                    onChange={(v) =>
                                        setControls({ realtime_monitoring: v })
                                    }
                                    error={err('questionnaire.controls.realtime_monitoring')}
                                />
                                <YesNoRow
                                    label="Kill switch to disable agents?"
                                    value={q.controls.kill_switch}
                                    onChange={(v) => setControls({ kill_switch: v })}
                                    error={err('questionnaire.controls.kill_switch')}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 11 — Risk &amp; failure handling</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Worst-case failure scenario for your agent</Label>
                                    <Textarea
                                        value={q.risk.worst_case_failure}
                                        onChange={(e) =>
                                            setRisk({ worst_case_failure: e.target.value })
                                        }
                                        rows={3}
                                        required
                                    />
                                    <InputError message={err('questionnaire.risk.worst_case_failure')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Incorrect payments — how you handle</Label>
                                    <Textarea
                                        value={q.risk.incorrect_payments}
                                        onChange={(e) =>
                                            setRisk({ incorrect_payments: e.target.value })
                                        }
                                        rows={2}
                                        required
                                    />
                                    <InputError message={err('questionnaire.risk.incorrect_payments')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Compromised accounts</Label>
                                    <Textarea
                                        value={q.risk.compromised_accounts}
                                        onChange={(e) =>
                                            setRisk({ compromised_accounts: e.target.value })
                                        }
                                        rows={2}
                                        required
                                    />
                                    <InputError message={err('questionnaire.risk.compromised_accounts')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Malicious inputs / prompt injection</Label>
                                    <Textarea
                                        value={q.risk.prompt_injection}
                                        onChange={(e) =>
                                            setRisk({ prompt_injection: e.target.value })
                                        }
                                        rows={2}
                                        required
                                    />
                                    <InputError message={err('questionnaire.risk.prompt_injection')} />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 12 — Technical integration</CardTitle>
                                <CardDescription>Select at least one integration type.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={q.integration.backend}
                                        onCheckedChange={(c) =>
                                            setIntegration({ backend: c === true })
                                        }
                                    />
                                    Backend server
                                </label>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={q.integration.client_side}
                                        onCheckedChange={(c) =>
                                            setIntegration({ client_side: c === true })
                                        }
                                    />
                                    Client-side (not recommended)
                                </label>
                                <InputError message={err('questionnaire.integration.backend')} />
                                <div className="grid gap-2">
                                    <Label>API use case description</Label>
                                    <Textarea
                                        value={q.integration.api_use_case}
                                        onChange={(e) =>
                                            setIntegration({ api_use_case: e.target.value })
                                        }
                                        rows={3}
                                        required
                                    />
                                    <InputError message={err('questionnaire.integration.api_use_case')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Webhook endpoint (optional)</Label>
                                    <Input
                                        type="url"
                                        value={q.integration.webhook_endpoint}
                                        onChange={(e) =>
                                            setIntegration({ webhook_endpoint: e.target.value })
                                        }
                                    />
                                    <InputError message={err('questionnaire.integration.webhook_endpoint')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Server region / hosting (optional)</Label>
                                    <Input
                                        value={q.integration.hosting_region}
                                        onChange={(e) =>
                                            setIntegration({ hosting_region: e.target.value })
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Section 13 — Declarations</CardTitle>
                                <CardDescription>You must accept all to submit.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <label className="flex items-start gap-2 text-sm">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={q.declarations.no_anonymous_financial}
                                        onCheckedChange={(c) =>
                                            setDeclarations({
                                                no_anonymous_financial: c === true,
                                            })
                                        }
                                    />
                                    I confirm I will not allow anonymous users to access financial
                                    functionality.
                                </label>
                                <label className="flex items-start gap-2 text-sm">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={q.declarations.aml_sanctions}
                                        onCheckedChange={(c) =>
                                            setDeclarations({ aml_sanctions: c === true })
                                        }
                                    />
                                    I confirm compliance with AML and sanctions laws.
                                </label>
                                <label className="flex items-start gap-2 text-sm">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={q.declarations.end_user_activity_responsibility}
                                        onCheckedChange={(c) =>
                                            setDeclarations({
                                                end_user_activity_responsibility: c === true,
                                            })
                                        }
                                    />
                                    I accept responsibility for end-user activity on my platform.
                                </label>
                                <label className="flex items-start gap-2 text-sm">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={q.declarations.terms_of_service}
                                        onCheckedChange={(c) =>
                                            setDeclarations({ terms_of_service: c === true })
                                        }
                                    />
                                    I agree to the Terms of Service.
                                </label>
                                <InputError
                                    message={err('questionnaire.declarations.no_anonymous_financial')}
                                />
                            </CardContent>
                        </Card>

                        <div className="flex flex-wrap gap-3">
                            <Button type="submit" disabled={form.processing}>
                                Submit for KYB review
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={company.settings.url()}>Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </CompanySettingsLayout>
        </AppLayout>
    );
}

function YesNoRow(props: {
    label: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{props.label}</Label>
            <Select
                value={props.value || '__empty__'}
                onValueChange={(v) => props.onChange(v === '__empty__' ? '' : v)}
            >
                <SelectTrigger>
                    <SelectValue placeholder="Select" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="__empty__">—</SelectItem>
                    <SelectItem value="yes">Yes</SelectItem>
                    <SelectItem value="no">No</SelectItem>
                </SelectContent>
            </Select>
            <InputError message={props.error} />
        </div>
    );
}
