import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { LensOption, PowerType } from '@/types/product';
import { send } from '@/lib/storefront';
import { LensDrawer } from './LensDrawer';

vi.mock('@/lib/storefront', async (original) => ({
    ...(await original<typeof import('@/lib/storefront')>()),
    send: vi.fn(() => new Promise(() => undefined)),
    toast: vi.fn(),
}));

const power = (code: string, label: string): PowerType => ({ code, label, description: '', price: 0, needsLens: true, needsRx: true });
const lens: LensOption = { code: 'antiglare', label: 'Anti-glare', description: '', price: 0, badge: null, features: [] };

const openPowerForm = async (powerLabel: string) => {
    const user = userEvent.setup();
    render(
        <LensDrawer productId={1} productPrice={100} finish={null} size="M" cartUrl="/cart" powerTypes={[power('single', 'Single vision'), power('progressive', 'Progressive')]}
                    lensOptions={[lens]} rxSubmissionDays={15} supportPhone={null} onClose={() => undefined} />,
    );
    await user.click(screen.getByRole('button', { name: new RegExp(powerLabel) }));
    await user.click(screen.getByRole('button', { name: /Anti-glare/ }));
    await user.click(screen.getByRole('button', { name: /Enter power manually/ }));
    return user;
};

const fillContact = async (user: ReturnType<typeof userEvent.setup>) => {
    await user.type(screen.getByLabelText('Patient name'), 'Ada Client');
    await user.type(screen.getByLabelText('Phone number'), '01712345678');
};

describe('LensDrawer prescription form', () => {
    beforeEach(() => {
        vi.mocked(send).mockClear();
    });

    it('requires the reading addition for progressive lenses and sends it', async () => {
        const user = await openPowerForm('Progressive');
        await fillContact(user);

        await user.click(screen.getByRole('button', { name: 'Save & Proceed' }));
        expect(screen.getByRole('alert').textContent).toContain('reading addition');
        expect(send).not.toHaveBeenCalled();

        await user.selectOptions(screen.getByLabelText('Reading addition (ADD)'), '+1.75');
        await user.type(screen.getByLabelText('Pupil distance in millimetres'), '63');
        await user.click(screen.getByRole('button', { name: 'Save & Proceed' }));

        const form = vi.mocked(send).mock.calls[0]![1] as FormData;
        expect(form.get('lens[rx][add_power]')).toBe('+1.75');
        expect(form.get('lens[rx][pd]')).toBe('63');
    });

    it('does not ask single vision customers for a reading addition', async () => {
        const user = await openPowerForm('Single vision');
        await fillContact(user);

        expect(screen.queryByLabelText('Reading addition (ADD)')).toBeNull();
        await user.click(screen.getByRole('button', { name: 'Save & Proceed' }));

        expect(send).toHaveBeenCalledOnce();
    });

    it('rejects an impossible pupil distance', async () => {
        const user = await openPowerForm('Single vision');
        await fillContact(user);

        await user.type(screen.getByLabelText('Pupil distance in millimetres'), '200');
        await user.click(screen.getByRole('button', { name: 'Save & Proceed' }));

        expect(screen.getByRole('alert').textContent).toContain('between 40 and 80');
        expect(send).not.toHaveBeenCalled();
    });
});
