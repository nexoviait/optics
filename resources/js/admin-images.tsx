import { createRoot } from 'react-dom/client';
import { ProductImagesManager, type ProductImagesManagerProps } from '@/components/admin/ProductImagesManager';

/** Mounts the React gallery manager on the (Blade) admin product edit page. */
const el = document.getElementById('product-images-manager');
if (el?.dataset.props) {
    createRoot(el).render(<ProductImagesManager {...(JSON.parse(el.dataset.props) as ProductImagesManagerProps)} />);
}
