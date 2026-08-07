import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { useBranding } from '../context/BrandingContext';

interface PageTitleProps {
  title: string;
}

const PageTitle: React.FC<PageTitleProps> = ({ title }) => {
  const location = useLocation();
  const { branding } = useBranding();

  useEffect(() => {
    const [pageName] = title.split('|');
    document.title = `${pageName.trim()} | ${branding.company_name || 'Sistema ERP'}`;
  }, [branding.company_name, location, title]);

  return null; // This component doesn't render anything
};

export default PageTitle;
