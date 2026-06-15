import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiLogOut, FiSettings, FiUser } from 'react-icons/fi';
import ClickOutside from '../ClickOutside';
import UserOne from '../../images/user/user-01.png';
import { useAuth } from '../../context/AuthContext';

const DropdownUser = () => {
  const navigate = useNavigate();
  const { logout, user } = useAuth();
  const [dropdownOpen, setDropdownOpen] = useState(false);
  const [signingOut, setSigningOut] = useState(false);

  const displayName = user?.full_name || user?.username || 'Usuario ERP';
  const roleName = user?.role?.name || 'Usuario';

  async function handleLogout() {
    setSigningOut(true);
    await logout();
    setSigningOut(false);
    setDropdownOpen(false);
    navigate('/auth/signin', { replace: true });
  }

  return (
    <ClickOutside onClick={() => setDropdownOpen(false)} className="relative">
      <button
        type="button"
        onClick={() => setDropdownOpen(!dropdownOpen)}
        className="flex items-center gap-4"
      >
        <span className="hidden text-right lg:block">
          <span className="block max-w-42 truncate text-sm font-medium text-black dark:text-white">
            {displayName}
          </span>
          <span className="block max-w-42 truncate text-xs">{roleName}</span>
        </span>

        <span className="h-12 w-12 overflow-hidden rounded-full border border-stroke bg-gray dark:border-strokedark">
          <img src={UserOne} alt={displayName} />
        </span>

        <svg
          className="hidden fill-current sm:block"
          width="12"
          height="8"
          viewBox="0 0 12 8"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            fillRule="evenodd"
            clipRule="evenodd"
            d="M0.410765 0.910734C0.736202 0.585297 1.26384 0.585297 1.58928 0.910734L6.00002 5.32148L10.4108 0.910734C10.7362 0.585297 11.2638 0.585297 11.5893 0.910734C11.9147 1.23617 11.9147 1.76381 11.5893 2.08924L6.58928 7.08924C6.26384 7.41468 5.7362 7.41468 5.41077 7.08924L0.410765 2.08922C0.0853277 1.76381 0.0853277 1.23617 0.410765 0.910734Z"
            fill=""
          />
        </svg>
      </button>

      {dropdownOpen && (
        <div className="absolute right-0 mt-4 flex w-64 flex-col rounded-md border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
          <div className="border-b border-stroke px-6 py-5 dark:border-strokedark">
            <p className="truncate text-sm font-semibold text-black dark:text-white">
              {displayName}
            </p>
            <p className="mt-1 truncate text-xs text-slate-500 dark:text-bodydark">
              {user?.email || user?.username}
            </p>
            {user?.company?.name && (
              <p className="mt-2 truncate text-xs font-medium text-primary">
                {user.company.name}
              </p>
            )}
          </div>

          <ul className="flex flex-col gap-1 px-3 py-3">
            <li>
              <Link
                to="/profile"
                className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium duration-300 ease-in-out hover:bg-gray hover:text-primary dark:hover:bg-meta-4"
              >
                <FiUser />
                Mi perfil
              </Link>
            </li>
            <li>
              <Link
                to="/settings"
                className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium duration-300 ease-in-out hover:bg-gray hover:text-primary dark:hover:bg-meta-4"
              >
                <FiSettings />
                Configuracion
              </Link>
            </li>
          </ul>

          <button
            type="button"
            onClick={handleLogout}
            disabled={signingOut}
            className="flex items-center gap-3 border-t border-stroke px-6 py-4 text-sm font-medium duration-300 ease-in-out hover:text-primary disabled:cursor-not-allowed disabled:opacity-70 dark:border-strokedark"
          >
            <FiLogOut />
            {signingOut ? 'Cerrando sesion...' : 'Cerrar sesion'}
          </button>
        </div>
      )}
    </ClickOutside>
  );
};

export default DropdownUser;
