
<div class="pcoded-main-container">
              <div class="pcoded-wrapper">
                  <nav class="pcoded-navbar">
                      <div class="sidebar_toggle"><a href="#"><i class="icon-close icons"></i></a></div>
                      <div class="pcoded-inner-navbar main-menu">
                           
                          <div class="p-15 p-b-0">
                             
                          </div>
                          <div class="pcoded-navigation-label" data-i18n="nav.category.navigation"></div>
                          <?php
                          $uri = service('uri');
                          $segment = $uri->getSegment(2); // Gets the first segment of the URI
                          ?>
                          <ul class="pcoded-item pcoded-left-item">
                          <li class="<?= ($segment == 'dashboard') ? 'active' : '' ?>">
                                  <a href="<?php echo base_url('admin/dashboard') ?>" class="waves-effect waves-dark">
                                      <span class="pcoded-micon"><i class="ti-home"></i><b>D</b></span>
                                      <span class="pcoded-mtext" data-i18n="nav.dash.main">Dashboard</span>
                                      <span class="pcoded-mcaret"></span>
                                  </a>
                              </li>
                              <li class="<?= ($segment == 'staff') ? 'active' : '' ?>">
                                    <a href="<?php echo base_url('admin/staff') ?>" class="waves-effect waves-dark">
                                        <span class="pcoded-micon"><i class="bi bi-person-add"></i><b>FC</b></span>
                                        <span class="pcoded-mtext" data-i18n="nav.form-components.main">App Users</span>
                                        <span class="pcoded-mcaret"></span>
                                    </a>
                                </li>
                                <li class="<?= ($segment == 'profile') ? 'active' : '' ?>">
                                    <a href="<?php echo base_url('admin/profile') ?>" class="waves-effect waves-dark">
                                        <span class="pcoded-micon"><i class="bi bi-person-circle"></i></span>
                                        <span class="pcoded-mtext" data-i18n="nav.form-components.main">My Profile</span>
                                        <span class="pcoded-mcaret"></span>
                                    </a>
                                </li>
                          </ul>
                          <ul class="pcoded-item pcoded-left-item">
                          <li class="<?= ($segment == 'category') ? 'active' : '' ?>">
                                  <a href="<?php echo base_url('admin/category') ?>" class="waves-effect waves-dark">
                                      <span class="pcoded-micon"><i class="bi bi-bookmark"></i><b>D</b></span>
                                      <span class="pcoded-mtext" data-i18n="nav.dash.main">Category</span>
                                      <span class="pcoded-mcaret"></span>
                                  </a>
                              </li>
                              
                              <li class="<?= ($segment == 'subcategory') ? 'active' : '' ?>">
                                <a href="<?php echo base_url('admin/subcategory') ?>" class="waves-effect waves-dark">
                                    <span class="pcoded-micon"><i class="bi bi-bookmark-plus"></i><b>D</b></span>
                                    <span class="pcoded-mtext" data-i18n="nav.dash.main">Sub Category</span>
                                    <span class="pcoded-mcaret"></span>
                                </a>
                            </li>
							<li class="<?= ($segment == 'accessory') ? 'active' : '' ?>">
                                <a href="<?php echo base_url('admin/accessory') ?>" class="waves-effect waves-dark">
                                    <span class="pcoded-micon"><i class="bi bi-gear"></i><b>D</b></span>
                                    <span class="pcoded-mtext" data-i18n="nav.dash.main">Accessories</span>
                                    <span class="pcoded-mcaret"></span>
                                </a>
                            </li>
							<li class="<?= ($segment == 'product') ? 'active' : '' ?>">
                                <a href="<?php echo base_url('admin/product') ?>" class="waves-effect waves-dark">
                                    <span class="pcoded-micon"><i class="bi bi-box-seam"></i><b>D</b></span>
                                    <span class="pcoded-mtext" data-i18n="nav.dash.main">Products</span>
                                    <span class="pcoded-mcaret"></span>
                                </a>
                            </li>
							<li class="<?= ($segment == 'customer') ? 'active' : '' ?>">
								<a href="<?php echo base_url('admin/customer') ?>" class="waves-effect waves-dark">
									<span class="pcoded-micon"><i class="bi bi-people"></i><b>FC</b></span>
									<span class="pcoded-mtext" data-i18n="nav.form-components.main">Customers</span>
									<span class="pcoded-mcaret"></span>
								</a>
							</li>
                            <li class="<?= ($segment == 'orders') ? 'active' : '' ?>">
                                <a href="<?php echo base_url('admin/orders') ?>" class="waves-effect waves-dark">
                                    <span class="pcoded-micon"><i class="bi bi-bag-heart"></i><b>D</b></span>
                                    <span class="pcoded-mtext" data-i18n="nav.dash.main">Projects</span>
                                    <span class="pcoded-mcaret"></span>
                                </a>
                            </li>
                            <li class="<?= ($segment == 'estimates') ? 'active' : '' ?>">
                                <a href="<?php echo base_url('admin/estimates') ?>" class="waves-effect waves-dark">
                                    <span class="pcoded-micon"><i class="bi bi-file-earmark-text"></i><b>E</b></span>
                                    <span class="pcoded-mtext">Estimates</span>
                                    <span class="pcoded-mcaret"></span>
                                </a>
                            </li>
                          </ul>
                  </nav>